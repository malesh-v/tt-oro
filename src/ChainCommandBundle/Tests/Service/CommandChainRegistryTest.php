<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\Tests\Service;

use App\ChainCommandBundle\Service\CommandChainRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

#[CoversClass(CommandChainRegistry::class)]
final class CommandChainRegistryTest extends TestCase
{
    private CommandChainRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandChainRegistry();
    }

    #[DataProvider('provideSingleMemberRegistrations')]
    #[Test]
    public function registerMemberReferenceAddsMemberToChain(string $mainCommand, string $memberName): void
    {
        $member = $this->mockCommand($memberName);

        $this->registry->registerMemberReference($mainCommand, $member);

        $members = $this->registry->getMembers($mainCommand);

        self::assertCount(1, $members);
        self::assertSame([$memberName], $members);
    }

    public static function provideSingleMemberRegistrations(): iterable
    {
        yield ['foo:hello', 'bar:hi'];
        yield ['bar:baz', 'qux:test'];
    }

    #[Test]
    public function registerMultipleMembersForSameMainCommand(): void
    {
        $mainCommand = 'foo:hello';
        $members = ['bar:hi', 'baz:test'];

        foreach ($members as $name) {
            $this->registry->registerMemberReference($mainCommand, $this->mockCommand($name));
        }

        $result = $this->registry->getMembers($mainCommand);

        self::assertCount(2, $result);
        self::assertSame($members, $result);
    }

    #[Test]
    public function registerMembersForDifferentMainCommands(): void
    {
        $map = [
            'foo:hello' => 'bar:hi',
            'baz:test' => 'qux:test',
        ];

        foreach ($map as $main => $member) {
            $this->registry->registerMemberReference($main, $this->mockCommand($member));
        }

        foreach ($map as $main => $member) {
            $members = $this->registry->getMembers($main);
            self::assertCount(1, $members);
            self::assertSame([$member], $members);
        }
    }

    #[Test]
    public function getMembersReturnsEmptyArrayWhenNoMembersRegistered(): void
    {
        $result = $this->registry->getMembers('non-existent:command');

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function getMainCommandForMemberReturnsMainCommandWhenMemberExists(): void
    {
        $mainCommand = 'foo:hello';
        $memberName = 'bar:hi';

        $this->registry->registerMemberReference($mainCommand, $this->mockCommand($memberName));

        self::assertSame($mainCommand, $this->registry->getMainCommandForMember($memberName));
    }

    #[Test]
    public function getMainCommandForMemberReturnsNullWhenMemberDoesNotExist(): void
    {
        self::assertNull($this->registry->getMainCommandForMember('ghost:cmd'));
    }

    #[Test]
    public function getMainCommandForMemberReturnsCorrectMainWhenMultipleChainsExist(): void
    {
        $map = [
            'foo:hello' => 'bar:hi',
            'baz:test' => 'qux:test',
        ];

        foreach ($map as $main => $member) {
            $this->registry->registerMemberReference($main, $this->mockCommand($member));
        }

        foreach ($map as $main => $member) {
            self::assertSame($main, $this->registry->getMainCommandForMember($member));
        }
    }

    #[Test]
    public function getMainCommandForMemberReturnsNullForMainCommandName(): void
    {
        $mainCommand = 'foo:hello';
        $this->registry->registerMemberReference($mainCommand, $this->mockCommand('bar:hi'));

        self::assertNull($this->registry->getMainCommandForMember($mainCommand));
    }

    #[Test]
    public function registerSameMemberTwiceDuplicatesEntry(): void
    {
        $mainCommand = 'foo:hello';
        $memberName = 'bar:hi';
        $member = $this->mockCommand($memberName);

        $this->registry->registerMemberReference($mainCommand, $member);
        $this->registry->registerMemberReference($mainCommand, $member);

        $members = $this->registry->getMembers($mainCommand);

        self::assertCount(2, $members);
        self::assertSame([$memberName, $memberName], $members);
    }

    private function mockCommand(string $name): Command
    {
        $mock = $this->createMock(Command::class);
        $mock->method('getName')->willReturn($name);

        return $mock;
    }
}
