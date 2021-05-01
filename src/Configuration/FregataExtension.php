<?php

namespace Fregata\Configuration;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * @internal
 */
class FregataExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        
        $this->createServiceDefinitions($container);
        
        $container->setParameter(MigrationsCompilerPass::PARAMETER_MIGRATIONS, $config['migrations']);
    }

    private function createServiceDefinitions(ContainerBuilder $container): void
    {
        // TODO: register base services like Migration class, ...
    }
}
