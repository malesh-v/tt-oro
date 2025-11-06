<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\Tests;

use App\ChainCommandBundle\ChainCommandBundle;
use App\ChainCommandBundle\DependencyInjection\Compiler\ChainCommandCompilerPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(ChainCommandBundle::class)]
final class ChainCommandBundleTest extends TestCase
{
    #[Test]
    public function buildAddsCompilerPass(): void
    {
        /** @var ContainerBuilder|MockObject $container */
        $container = $this->createMock(ContainerBuilder::class);

        // Expect the compiler pass to be added exactly once with correct class
        $container->expects($this->once())
            ->method('addCompilerPass')
            ->with(self::callback(static function ($pass) {
                return $pass instanceof ChainCommandCompilerPass;
            }));

        $model = new ChainCommandBundle();
        $model->build($container);
    }
}
