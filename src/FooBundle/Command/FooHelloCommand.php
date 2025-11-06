<?php

declare(strict_types=1);

namespace App\FooBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The main Foo command — acts as the main command in the chain.
 */
#[AsCommand(
    name: 'foo:hello',
    description: 'Main command FooHelloCommand.'
)]
final class FooHelloCommand extends Command
{
    /**
     * Executes a command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello from Foo!');

        return Command::SUCCESS;
    }
}
