<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\DependencyInjection\Compiler;

use App\ChainCommandBundle\Service\CommandChainRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ChainCommandCompilerPass implements CompilerPassInterface
{
    private const TAG_NAME = 'chain.command.member';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(CommandChainRegistry::class)) {
            return;
        }

        $registry = $container->getDefinition(CommandChainRegistry::class);

        foreach ($container->findTaggedServiceIds(self::TAG_NAME) as $id => $tags) {
            foreach ($tags as $tag) {
                $mainCommand = $tag['main-command'] ?? null;
                if ($mainCommand === null) {
                    continue;
                }

                $container->getDefinition($id)
                    ->addMethodCall('setHidden', [true]);

                $registry->addMethodCall('registerMemberReference', [$mainCommand, new Reference($id)]);
            }
        }
    }
}
