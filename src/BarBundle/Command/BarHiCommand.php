<?php

declare(strict_types=1);

namespace App\BarBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Member command that will be part of foo:hello chain.
 */
#[AsCommand(
    name: 'bar:hi',
    description: 'Member command of foo:hello chain.'
)]
#[AutoconfigureTag('chain.command.member', ['main-command' => 'foo:hello'])]
final class BarHiCommand extends Command
{
    private const TEXT_TO_PRINT = 'Hi from Bar!';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    /**
     * Executes a command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(self::TEXT_TO_PRINT);
        $this->logger->info(self::TEXT_TO_PRINT);

        return Command::SUCCESS;
    }
}
