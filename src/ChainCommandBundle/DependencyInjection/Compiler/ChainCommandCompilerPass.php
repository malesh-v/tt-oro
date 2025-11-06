<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\DependencyInjection\Compiler;

use App\ChainCommandBundle\Service\CommandChainRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to register command to the main command.
 */
final class ChainCommandCompilerPass implements CompilerPassInterface
{
    private const TAG_NAME = 'chain.command.member';

    /**
     * Register command to the main command.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(CommandChainRegistry::class)) {
            return;
        }

        $registry = $container->getDefinition(CommandChainRegistry::class);

        foreach ($container->findTaggedServiceIds(self::TAG_NAME) as $id => $tags) {
            foreach ($tags as $tag) {
                $mainCommand = $tag['main-command'] ?? null;
                if (empty($mainCommand)) {
                    continue;
                }

                $container->getDefinition($id)
                    ->addMethodCall('setHidden', [true]);

                $registry->addMethodCall('registerMemberReference', [$mainCommand, new Reference($id)]);
            }
        }
    }
}
