<?php

declare(strict_types=1);

namespace App\ChainCommandBundle;

use App\ChainCommandBundle\DependencyInjection\Compiler\ChainCommandCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle that provides command chaining functionality.
 */
final class ChainCommandBundle extends Bundle
{
    /**
     * Add compiler pass to register chain members after all bundles are loaded.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ChainCommandCompilerPass());
    }
}
