<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\Service;

/**
 * Registry that stores relationships between main and member commands.
 */
final class CommandChainRegistry
{
    private array $chains = [];

    /**
     * Registers a member command to a main command chain.
     */
    public function register(string $mainCommandName, string $memberCommandName): void
    {
        $this->chains[$mainCommandName][] = $memberCommandName;
    }

    /**
     * Returns all member commands registered for the given main command.
     */
    public function getMembers(string $mainCommandName): array
    {
        return $this->chains[$mainCommandName] ?? [];
    }

    /**
     * Returns all chains currently registered.
     */
    public function all(): array
    {
        return $this->chains;
    }
}
