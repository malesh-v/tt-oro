<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\Service;

use Symfony\Component\Console\Command\Command;

/**
 * Registry that stores relationships between main and member commands.
 */
final class CommandChainRegistry
{
    /**
     * @var array<string, list<string>> maps main command name => list of member command names
     */
    private array $chains = [];

    /**
     * Registers a member command to a main command.
     */
    public function registerMemberReference(string $mainCommandName, Command $memberReference): void
    {
        $this->chains[$mainCommandName][] = $memberReference->getName();
    }

    /**
     * Returns all member commands registered for the given main command.
     *
     * @return list<string>
     */
    public function getMembers(string $mainCommandName): array
    {
        return $this->chains[$mainCommandName] ?? [];
    }

    /**
     * Returns the main command name if the given command is a member, or null.
     */
    public function getMainCommandForMember(string $memberName): ?string
    {
        foreach ($this->chains as $mainCommand => $members) {
            if (\in_array($memberName, $members, true)) {
                return $mainCommand;
            }
        }

        return null;
    }
}
