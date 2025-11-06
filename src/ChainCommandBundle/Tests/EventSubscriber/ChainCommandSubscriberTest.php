<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\Tests\EventSubscriber;

use App\ChainCommandBundle\EventSubscriber\ChainCommandSubscriber;
use App\ChainCommandBundle\Service\CommandChainRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(ChainCommandSubscriber::class)]
final class ChainCommandSubscriberTest extends TestCase
{
    private CommandChainRegistry $registry;
    private ChainCommandSubscriber $subscriber;
    private Application $application;
    private array $logRecords = [];

    protected function setUp(): void
    {
        $this->registry = new CommandChainRegistry();
        $this->application = new Application();
        $this->logRecords = [];

        $this->subscriber = new ChainCommandSubscriber(
            $this->registry,
            $this->createLogger()
        );
    }

    #[Test]
    public function getSubscribedEventsReturnsCorrectEvents(): void
    {
        $events = ChainCommandSubscriber::getSubscribedEvents();

        self::assertSame('onCommand', $events[ConsoleEvents::COMMAND]);
        self::assertSame('onTerminate', $events[ConsoleEvents::TERMINATE]);
    }

    #[Test]
    public function onCommandPreventsExecutionOfMemberCommand(): void
    {
        $mainName = 'foo:hello';
        $memberName = 'bar:hi';
        $member = $this->createMockCommand($memberName);

        $this->registry->registerMemberReference($mainName, $member);

        $output = new BufferedOutput();
        $event = $this->createCommandEvent($member, $output);

        $this->subscriber->onCommand($event);

        self::assertFalse($event->commandShouldRun());

        $content = $output->fetch();

        self::assertStringContainsString('Error:', $content);
        self::assertStringContainsString($memberName, $content);
        self::assertStringContainsString($mainName, $content);
        self::assertStringContainsString('cannot be executed on its own', $content);
    }

    #[Test]
    public function onCommandDoesNothingWhenCommandIsNull(): void
    {
        $event = $this->createCommandEvent(null, new BufferedOutput());

        $this->subscriber->onCommand($event);

        self::assertEmpty($this->logRecords);
    }

    #[Test]
    public function onCommandLogsChainInfoForMainCommand(): void
    {
        $mainName = 'foo:hello';
        $memberName = 'bar:hi';

        $mainCmd = $this->createMockCommand($mainName);
        $memberCmd = $this->createMockCommand($memberName);

        $this->registry->registerMemberReference($mainName, $memberCmd);

        $event = $this->createCommandEvent($mainCmd, new BufferedOutput());

        $this->subscriber->onCommand($event);

        $this->assertLogContains('is a master command of a command chain');
        $this->assertLogContains('registered as a member of');
        $this->assertLogContains("Executing {$mainName} command itself first:");
    }

    #[Test]
    public function onCommandDoesNothingForNonChainCommand(): void
    {
        $command = $this->createMockCommand('standalone:command');
        $event = $this->createCommandEvent($command, new BufferedOutput());

        $this->subscriber->onCommand($event);

        self::assertEmpty($this->logRecords);
    }

    #[Test]
    public function onTerminateExecutesChainMembersOnSuccess(): void
    {
        $mainName = 'foo:hello';
        $memberName = 'bar:hi';

        $mainCmd = $this->createMockCommand($mainName);
        $memberCmd = $this->createMockCommand($memberName);

        $this->registry->registerMemberReference($mainName, $memberCmd);
        $this->application->add($memberCmd);

        $event = $this->createTerminateEvent($mainCmd, Command::SUCCESS);

        $this->subscriber->onTerminate($event);

        $this->assertLogContains("Executing {$mainName} chain members:");
        $this->assertLogContains("Execution of {$mainName} chain completed.");
    }

    #[DataProvider('provideFailureExitCodes')]
    #[Test]
    public function onTerminateDoesNotExecuteChainOnFailure(int $exitCode): void
    {
        $mainName = 'foo:hello';
        $memberName = 'bar:hi';

        $mainCmd = $this->createMockCommand($mainName);
        $memberCmd = $this->createMockCommand($memberName);

        $this->registry->registerMemberReference($mainName, $memberCmd);

        $event = $this->createTerminateEvent($mainCmd, $exitCode);

        $this->subscriber->onTerminate($event);

        $this->assertLogDoesNotContain('Executing');
    }

    public static function provideFailureExitCodes(): iterable
    {
        yield 'failure status' => [Command::FAILURE];
        yield 'invalid status' => [Command::INVALID];
        yield 'custom error code' => [1];
        yield 'another error code' => [255];
    }

    #[Test]
    public function onTerminateHandlesMissingMemberCommand(): void
    {
        $mainName = 'foo:hello';
        $missingName = 'non-existent:command';

        $application = $this->createMock(Application::class);
        $application->method('find')
            ->with($missingName)
            ->willThrowException(new CommandNotFoundException("Command \"{$missingName}\" not found."));

        $mainCmd = $this->createMockCommand($mainName, $application);
        $memberCmd = $this->createMockCommand($missingName);

        $this->registry->registerMemberReference($mainName, $memberCmd);

        $output = new BufferedOutput();
        $event = $this->createTerminateEvent($mainCmd, Command::SUCCESS, $output);

        $this->subscriber->onTerminate($event);

        $content = $output->fetch();

        self::assertStringContainsString('Member command', $content);
        self::assertStringContainsString($missingName, $content);
        self::assertStringContainsString('not found', $content);
    }

    #[Test]
    public function onTerminateHandlesCommandException(): void
    {
        $mainName = 'foo:hello';
        $memberName = 'bar:hi';

        $memberCmd = $this->createMockCommand($memberName);
        $memberCmd->method('run')
            ->willThrowException(new \RuntimeException('Command execution failed'));

        $application = $this->createMock(Application::class);
        $application->method('find')
            ->with($memberName)
            ->willReturn($memberCmd);

        $mainCmd = $this->createMockCommand($mainName, $application);

        $this->registry->registerMemberReference($mainName, $memberCmd);

        $output = new BufferedOutput();
        $event = $this->createTerminateEvent($mainCmd, Command::SUCCESS, $output);

        $this->subscriber->onTerminate($event);

        $content = $output->fetch();

        self::assertStringContainsString('Unexpected error', $content);
        self::assertStringContainsString($memberName, $content);
        self::assertStringContainsString('Command execution failed', $content);
    }

    #[Test]
    public function onTerminateDoesNothingWhenNoMembersRegistered(): void
    {
        $mainCmd = $this->createMockCommand('foo:hello');
        $event = $this->createTerminateEvent($mainCmd, Command::SUCCESS);

        $this->subscriber->onTerminate($event);

        $this->assertLogDoesNotContain('Executing');
    }

    #[Test]
    public function onTerminateExecutesMultipleMembersInOrder(): void
    {
        $mainName = 'foo:hello';
        $member1Name = 'bar:hi';
        $member2Name = 'baz:test';
        $executionOrder = [];

        $member1 = $this->createMockCommandWithCallback(
            $member1Name,
            static function () use (&$executionOrder, $member1Name): int {
                $executionOrder[] = $member1Name;

                return Command::SUCCESS;
            }
        );

        $member2 = $this->createMockCommandWithCallback(
            $member2Name,
            static function () use (&$executionOrder, $member2Name): int {
                $executionOrder[] = $member2Name;

                return Command::SUCCESS;
            }
        );

        $application = $this->createMock(Application::class);
        $application->method('find')->willReturnMap([
            [$member1Name, $member1],
            [$member2Name, $member2],
        ]);

        $mainCmd = $this->createMockCommand($mainName, $application);

        $this->registry->registerMemberReference($mainName, $member1);
        $this->registry->registerMemberReference($mainName, $member2);

        $event = $this->createTerminateEvent($mainCmd, Command::SUCCESS);

        $this->subscriber->onTerminate($event);

        self::assertSame([$member1Name, $member2Name], $executionOrder);
    }

    #[DataProvider('provideNullApplicationScenarios')]
    public function testOnTerminateDoesNothingWhenApplicationIsNull(
        string $mainName,
        string $memberName,
    ): void {
        $mainCmd = $this->createMock(Command::class);
        $mainCmd->method('getName')->willReturn($mainName);
        $mainCmd->method('getApplication')->willReturn(null);

        $memberCmd = $this->createMockCommand($memberName);

        $this->registry->registerMemberReference($mainName, $memberCmd);

        $event = $this->createTerminateEvent($mainCmd, Command::SUCCESS);

        $this->subscriber->onTerminate($event);

        $this->assertLogDoesNotContain('chain members:');
        $this->assertLogDoesNotContain('chain completed.');
    }

    public static function provideNullApplicationScenarios(): iterable
    {
        yield 'standard commands' => ['foo:hello', 'bar:hi'];
        yield 'namespaced commands' => ['app:main', 'app:member'];
    }

    private function createLogger(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info')
            ->willReturnCallback(fn (string $message): true => (bool) ($this->logRecords[] = $message));

        return $logger;
    }

    private function createCommandEvent(?Command $command, BufferedOutput $output): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent(
            $command,
            $this->createMock(InputInterface::class),
            $output
        );
    }

    private function createTerminateEvent(
        Command $command,
        int $exitCode,
        ?BufferedOutput $output = null,
    ): ConsoleTerminateEvent {
        return new ConsoleTerminateEvent(
            $command,
            $this->createMock(InputInterface::class),
            $output ?? new BufferedOutput(),
            $exitCode
        );
    }

    private function createMockCommand(string $name, ?Application $application = null): Command
    {
        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn($name);
        $command->method('getApplication')->willReturn($application ?? $this->application);
        $command->method('run')->willReturn(Command::SUCCESS);

        return $command;
    }

    private function createMockCommandWithCallback(string $name, callable $runCallback): Command
    {
        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn($name);
        $command->method('run')->willReturnCallback($runCallback);

        return $command;
    }

    private function assertLogContains(string $needle): void
    {
        self::assertNotEmpty(
            array_filter(
                $this->logRecords,
                static fn (string $message): bool => str_contains($message, $needle)
            ),
            "Failed asserting that log contains: {$needle}"
        );
    }

    private function assertLogDoesNotContain(string $needle): void
    {
        self::assertEmpty(
            array_filter(
                $this->logRecords,
                static fn (string $message): bool => str_contains($message, $needle)
            ),
            "Failed asserting that log does not contain: {$needle}"
        );
    }
}
