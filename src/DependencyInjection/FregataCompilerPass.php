<?php

namespace Fregata\DependencyInjection;

use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\Component\Executor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class FregataCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // Migration registry
        $registryDefinition = new Definition(MigrationRegistry::class);
        $container->setDefinition('fregata.migration_registry', $registryDefinition);
        $container
            ->setDefinition(MigrationRegistry::class, $registryDefinition)
            ->setPublic(true)
        ;

        // Add migrations to the registry
        foreach ($container->findTaggedServiceIds('fregata.migration') as $migrationServiceId => $tags) {
            $registryDefinition = $container->getDefinition('fregata.migration_registry');
            $registryDefinition->addMethodCall('add', [
                $tags[0]['name'],
                new Reference($migrationServiceId)
            ]);
        }

        // Default executor
        $executorDefinition = new Definition(Executor::class);
        $container->setDefinition('fregata.executor', $executorDefinition);
        $container
            ->setDefinition(Executor::class, $executorDefinition)
            ->setPublic(true)
        ;

        // Column helper
        $columnHelperDefinition = new Definition(CopyColumnHelper::class);
        $container->setDefinition('fregata.doctrine.dbal.column_helper', $columnHelperDefinition);
        $container
            ->setDefinition(CopyColumnHelper::class, $columnHelperDefinition)
            ->setPublic(true)
        ;
    }
}
