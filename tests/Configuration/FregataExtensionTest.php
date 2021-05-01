<?php

namespace Fregata\Tests\Configuration;

use Fregata\Configuration\FregataExtension;
use Fregata\Configuration\MigrationsCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FregataExtensionTest extends TestCase
{
    /**
     * Ensures migrations configuration is saved in container
     */
    public function testMigrationsConfigurationIsSaved()
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $extension->load([], $container);
        self::assertNotNull($container->getParameter(MigrationsCompilerPass::PARAMETER_MIGRATIONS));
    }
}
