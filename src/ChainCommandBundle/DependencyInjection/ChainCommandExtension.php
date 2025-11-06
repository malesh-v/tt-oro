<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Loads and manages the ChainCommandBundle service configuration.
 */
final class ChainCommandExtension extends Extension
{
    /**
     * Loads bundle configuration and registers services in the container.
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        // TODO add load configuration and register services.
    }
}
