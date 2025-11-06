<?php

declare(strict_types=1);

namespace App\FooBundle\Command;

use Psr\Log\LoggerInterface;
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
    private const TEXT_TO_PRINT = 'Hello from Foo!';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info(self::TEXT_TO_PRINT);
        $output->writeln(self::TEXT_TO_PRINT);

        return Command::SUCCESS;
    }
}
