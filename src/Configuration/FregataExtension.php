<?php

namespace Fregata\Configuration;

use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\MigratorInterface;
use hanneskod\classtools\Iterator\ClassIterator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;

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

        foreach ($config['migrations'] as $name => $config) {
            $config['name'] = $name;
            $this->registerMigration($container, $config);
        }
    }

    private function createServiceDefinitions(ContainerBuilder $container): void
    {
        // Migration registry
        $container
            ->setDefinition('fregata.migration_registry', new Definition(MigrationRegistry::class))
            ->setPublic(true)
        ;

        // Base migration service
        $container
            ->setDefinition('fregata.migration', new Definition(Migration::class))
            ->setPublic(false)
        ;
    }

    private function registerMigration(ContainerBuilder $container, array $migrationConfig): void
    {
        // Migrators classes
        $migratorClasses = [];
        if (null !== $migrationConfig['migrators_directory']) {
            $migratorClasses = $this->findMigratorsInDirectory($migrationConfig['migrators_directory']);
        }
        $migratorClasses = array_merge($migratorClasses, $migrationConfig['migrators']);

        // Migration definition with migrators
        $migration = new ChildDefinition('fregata.migration');

        foreach ($migratorClasses as $migratorClass) {
            $migrator = new Definition($migratorClass);
            $migrator->setAutowired(true);
            $container->setDefinition($migratorClass, $migrator);

            $migration->addMethodCall('add', [new Reference($migratorClass)]);
        }

        // Before tasks
        if (null !== $migrationConfig['tasks']['before']) {
            foreach ($migrationConfig['tasks']['before'] as $beforeTaskClass) {
                $task = new Definition($beforeTaskClass);
                $container->setDefinition($beforeTaskClass, $task);

                $migration->addMethodCall('addBeforeTask', [new Reference($beforeTaskClass)]);
            }
        }

        // After tasks
        if (null !== $migrationConfig['tasks']['after']) {
            foreach ($migrationConfig['tasks']['after'] as $afterTaskClass) {
                $task = new Definition($afterTaskClass);
                $container->setDefinition($afterTaskClass, $task);

                $migration->addMethodCall('addAfterTask', [new Reference($afterTaskClass)]);
            }
        }

        // Add migration to the registry
        $migrationId = 'fregata.migration.' . $migrationConfig['name'];
        $container->setDefinition($migrationId, $migration);

        $registry = $container->getDefinition('fregata.migration_registry');
        $registry->addMethodCall('add', [
            $migrationConfig['name'],
            new Reference($migrationId)
        ]);
    }

    private function findMigratorsInDirectory(string $path): array
    {
        $finder = new Finder();
        $iterator = new ClassIterator($finder->in($path));
        $iterator->enableAutoloading();

        $iterator = $iterator->type(MigratorInterface::class);

        /** @var ClassIterator $iterator */
        $iterator = $iterator->where('isInstantiable', true);

        $classes = [];

        /** @var \ReflectionClass $class */
        foreach ($iterator as $class) {
            $classes[] = $class->getName();
        }

        return $classes;
    }
}
