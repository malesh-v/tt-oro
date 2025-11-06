<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\Tests\DependencyInjection\Compiler;

use App\ChainCommandBundle\DependencyInjection\Compiler\ChainCommandCompilerPass;
use App\ChainCommandBundle\Service\CommandChainRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(ChainCommandCompilerPass::class)]
final class ChainCommandCompilerPassTest extends TestCase
{
    private ChainCommandCompilerPass $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new ChainCommandCompilerPass();
    }

    #[DataProvider('provideValidMemberCommands')]
    #[Test]
    public function registersMemberCommandWhenRegistryExists(array $tagAttributes, string $expectedMainCommand): void
    {
        $container = $this->createContainerWithRegistry();
        $member = $this->createCommandDefinition();
        $member->addTag('chain.command.member', $tagAttributes);

        $container->setDefinition('member.command', $member);

        $this->compilerPass->process($container);

        $this->assertCommandIsHidden($member);

        $registry = $container->getDefinition(CommandChainRegistry::class);

        self::assertNotEmpty(
            $registry->getMethodCalls(),
            'Expected at least one registerMemberReference() call.'
        );
        $this->assertRegistryHasCall($registry, 'registerMemberReference', $expectedMainCommand);
    }

    public static function provideValidMemberCommands(): iterable
    {
        yield 'single valid tag' => [['main-command' => 'foo:hello'], 'foo:hello'];
        yield 'another valid tag' => [['main-command' => 'bar:baz'], 'bar:baz'];
    }

    #[Test]
    public function skipsWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();
        $member = $this->createCommandDefinition();
        $member->addTag('chain.command.member', ['main-command' => 'foo:hello']);

        $container->setDefinition('member.command', $member);

        $this->compilerPass->process($container);

        self::assertEmpty($member->getMethodCalls(), 'Should skip when registry is not registered.');
    }

    #[DataProvider('provideInvalidTagAttributes')]
    #[Test]
    public function skipsInvalidMainCommandAttributes(array $attributes): void
    {
        $container = $this->createContainerWithRegistry();
        $member = $this->createCommandDefinition();
        $member->addTag('chain.command.member', $attributes);

        $container->setDefinition('invalid.member.command', $member);

        $this->compilerPass->process($container);

        self::assertEmpty($member->getMethodCalls(), 'No method calls expected for invalid tag attributes.');
    }

    public static function provideInvalidTagAttributes(): iterable
    {
        yield 'missing main-command' => [[]];
        yield 'null main-command' => [['main-command' => null]];
        yield 'empty main-command' => [['main-command' => '']];
    }

    #[Test]
    public function handlesMultipleTagsOnSameService(): void
    {
        $container = $this->createContainerWithRegistry();
        $member = $this->createCommandDefinition();
        $member->addTag('chain.command.member', ['main-command' => 'foo:hello']);
        $member->addTag('chain.command.member', ['main-command' => 'bar:baz']);

        $container->setDefinition('multi.member.command', $member);

        $this->compilerPass->process($container);

        $this->assertCommandIsHidden($member);

        $registry = $container->getDefinition(CommandChainRegistry::class);

        self::assertNotEmpty($registry->getMethodCalls(), 'Registry should receive calls for multiple tags.');
        self::assertCount(2, $registry->getMethodCalls(), 'Expected two registerMemberReference() calls.');
    }

    #[Test]
    public function handlesMultipleMembersForSameMainCommand(): void
    {
        $container = $this->createContainerWithRegistry();

        $this->addMemberCommand($container, 'member1', 'foo:hello');
        $this->addMemberCommand($container, 'member2', 'foo:hello');

        $this->compilerPass->process($container);

        $registry = $container->getDefinition(CommandChainRegistry::class);
        $calls = $registry->getMethodCalls();

        self::assertCount(2, $calls, 'Expected two registerMemberReference() calls.');

        foreach ($calls as [$method, $args]) {
            self::assertSame('registerMemberReference', $method);
            self::assertSame('foo:hello', $args[0]);
        }
    }

    #[Test]
    public function ignoresServicesWithoutMemberTag(): void
    {
        $container = $this->createContainerWithRegistry();
        $command = $this->createCommandDefinition();
        $command->addTag('console.command');

        $container->setDefinition('regular.command', $command);

        $this->compilerPass->process($container);

        $registry = $container->getDefinition(CommandChainRegistry::class);

        self::assertEmpty($registry->getMethodCalls(), 'Registry should not have any calls for non-member commands.');
    }

    private function createContainerWithRegistry(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $registry = new Definition(CommandChainRegistry::class);
        $container->setDefinition(CommandChainRegistry::class, $registry);

        return $container;
    }

    private function createCommandDefinition(string $class = Command::class): Definition
    {
        return new Definition($class);
    }

    private function addMemberCommand(ContainerBuilder $container, string $serviceId, string $mainCommand): void
    {
        $member = $this->createCommandDefinition();
        $member->addTag('chain.command.member', ['main-command' => $mainCommand]);
        $container->setDefinition($serviceId, $member);
    }

    private function assertCommandIsHidden(Definition $definition): void
    {
        $methodCalls = $definition->getMethodCalls();
        $hasSetHidden = false;

        foreach ($methodCalls as [$method, $args]) {
            if ('setHidden' === $method && true === $args[0]) {
                $hasSetHidden = true;
                break;
            }
        }

        self::assertTrue($hasSetHidden, 'Expected command to be hidden via setHidden(true).');
    }

    private function assertRegistryHasCall(Definition $registry, string $expectedMethod, string $expectedMainCommand): void
    {
        $calls = $registry->getMethodCalls();
        $found = false;

        foreach ($calls as [$method, $args]) {
            if ($method === $expectedMethod && isset($args[0]) && $args[0] === $expectedMainCommand) {
                $found = true;
                break;
            }
        }

        self::assertTrue(
            $found,
            \sprintf('Registry should have method call: %s for command %s', $expectedMethod, $expectedMainCommand)
        );
    }
}
