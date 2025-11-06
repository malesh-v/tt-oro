<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\EventSubscriber;

use App\ChainCommandBundle\Service\CommandChainRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to Symfony console events to manage command chain execution.
 */
final readonly class ChainCommandSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CommandChainRegistry $registry,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Returns the events this subscriber listens to.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    /**
     * Prevents execution of commands that are registered as members of a chain.
     */
    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$command) {
            return;
        }

        $commandName = $command->getName();

        // Prevent direct execution of member commands
        $mainCommand = $this->registry->getMainCommandForMember($commandName);
        if (null !== $mainCommand) {
            $event->getOutput()->writeln(\sprintf(
                '<error>Error: "%s" command is a member of "%s" command chain and cannot be executed on its own.</error>',
                $commandName,
                $mainCommand
            ));

            $event->disableCommand();

            return;
        }

        // Do nothing when no registered members
        if (empty($this->registry->getMembers($commandName))) {
            return;
        }

        $this->logCommandChainInfo($commandName);
    }

    /**
     * Executes member commands after the main command terminates successfully.
     */
    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if (false === $this->shouldRunChain($event)) {
            return;
        }

        $command = $event->getCommand();
        $application = $command?->getApplication();
        $commandName = $command?->getName();

        if (!$application || !$commandName || empty($this->registry->getMembers($commandName))) {
            return;
        }

        $output = $event->getOutput();

        $this->logger->info(\sprintf('Executing %s chain members:', $commandName));

        foreach ($this->registry->getMembers($commandName) as $memberName) {
            $this->runMemberCommandWithHandling($application, $memberName, $output);
        }

        $this->logger->info(\sprintf('Execution of %s chain completed.', $commandName));
    }

    /**
     * Determines whether the chain should be executed.
     */
    private function shouldRunChain(ConsoleTerminateEvent $event): bool
    {
        return Command::SUCCESS === $event->getExitCode()
            && null !== $event->getCommand();
    }

    /**
     * Executes a member command and handles errors.
     */
    private function runMemberCommandWithHandling(
        Application $application,
        string $memberName,
        OutputInterface $output,
    ): void {
        $errorMessage = null;

        try {
            $application->find($memberName)
                ->run(new ArrayInput([]), $output);
        } catch (CommandNotFoundException $e) {
            $errorMessage = \sprintf('Member command "%s" not found: %s', $memberName, $e->getMessage());
        } catch (ExceptionInterface $e) {
            $errorMessage = \sprintf('Member command "%s" failed: %s', $memberName, $e->getMessage());
        } catch (\Throwable $e) {
            $errorMessage = \sprintf('Unexpected error in member command "%s": %s', $memberName, $e->getMessage());
        }

        if (null !== $errorMessage) {
            $output->writeln(\sprintf('<error>%s</error>', $errorMessage));
        }
    }

    /**
     * Log chain related info.
     */
    private function logCommandChainInfo(string $commandName): void
    {
        $this->logger->info(\sprintf(
            '%s is a master command of a command chain that has registered member commands',
            $commandName
        ));

        foreach ($this->registry->getMembers($commandName) as $memberName) {
            $this->logger->info(\sprintf(
                '%s registered as a member of %s command chain',
                $memberName,
                $commandName
            ));
        }

        $this->logger->info(\sprintf('Executing %s command itself first:', $commandName));
    }
}
