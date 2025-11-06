<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\EventSubscriber;

use App\ChainCommandBundle\Service\CommandChainRegistry;
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
use Throwable;

/**
 * Listens to Symfony console events to manage command chain execution.
 */
final readonly class ChainCommandSubscriber implements EventSubscriberInterface
{
    public function __construct(private CommandChainRegistry $registry)
    {
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
        $mainCommand = $this->registry->getMainCommandForMember($commandName);
        if ($mainCommand === null) {
            return;
        }

        $event->getOutput()->writeln(sprintf(
            '<error>Error: "%s" command is a member of "%s" command chain and cannot be executed on its own.</error>',
            $commandName,
            $mainCommand
        ));

        $event->disableCommand();
    }

    /**
     * Executes member commands after the main command terminates successfully.
     */
    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if ($this->shouldRunChain($event) === false) {
            return;
        }

        $command = $event->getCommand();
        $application = $command?->getApplication();
        $commandName = $command?->getName();

        if (!$application || !$commandName) {
            return;
        }

        $output = $event->getOutput();

        foreach ($this->registry->getMembers($commandName) as $memberName) {
            $this->runMemberCommandWithHandling($application, $memberName, $output);
        }
    }

    /**
     * Determines whether the chain should be executed.
     */
    private function shouldRunChain(ConsoleTerminateEvent $event): bool
    {
        return $event->getExitCode() === Command::SUCCESS
            && $event->getCommand() !== null;
    }

    /**
     * Executes a member command and handles errors.
     */
    private function runMemberCommandWithHandling(
        Application $application,
        string $memberName,
        OutputInterface $output
    ): void {
        $errorMessage = null;

        try {
            $application->find($memberName)
                ->run(new ArrayInput([]), $output);
        } catch (CommandNotFoundException $e) {
            $errorMessage = sprintf('Member command "%s" not found: %s', $memberName, $e->getMessage());
        } catch (ExceptionInterface $e) {
            $errorMessage = sprintf('Member command "%s" failed: %s', $memberName, $e->getMessage());
        } catch (Throwable $e) {
            $errorMessage = sprintf('Unexpected error in member command "%s": %s', $memberName, $e->getMessage());
        }

        if ($errorMessage !== null) {
            $output->writeln(sprintf('<error>%s</error>', $errorMessage));
        }
    }
}
