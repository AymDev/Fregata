<?php

namespace Fregata\Configuration;

use Fregata\Migration\Migration;
use Fregata\Migration\MigrationContext;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\MigratorInterface;
use hanneskod\classtools\Iterator\ClassIterator;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;
use Symfony\Component\String\UnicodeString;

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
        $registryDefinition = new Definition(MigrationRegistry::class);
        $container->setDefinition('fregata.migration_registry', $registryDefinition);
        $container
            ->setDefinition(MigrationRegistry::class, $registryDefinition)
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
        // Migration definition
        $migrationDefinition = new ChildDefinition('fregata.migration');
        $migrationId = 'fregata.migration.' . $migrationConfig['name'];
        $container->setDefinition($migrationId, $migrationDefinition);

        // Add migration to the registry
        $registry = $container->getDefinition('fregata.migration_registry');
        $registry->addMethodCall('add', [
            $migrationConfig['name'],
            new Reference($migrationId)
        ]);

        // Migration context
        $contextDefinition = new Definition(MigrationContext::class);
        $contextDefinition->setArguments([
            new Reference($migrationId),
            $migrationConfig['name'],
            $migrationConfig['options'],
        ]);
        $contextId = $migrationId . '.context';
        $container->setDefinition($contextId, $contextDefinition);

        // Migrator definitions
        $migrators = [];
        if (null !== $migrationConfig['migrators_directory']) {
            $migrators = $this->findMigratorsInDirectory($migrationConfig['migrators_directory']);
        }
        $migrators = array_merge($migrators, $migrationConfig['migrators']);

        foreach ($migrators as $migratorClass) {
            $migratorDefinition = new Definition($migratorClass);
            $migratorId = $migrationId . '.migrator.' . (new UnicodeString($migratorClass))->snake();
            $container->setDefinition($migratorId, $migratorDefinition);

            $migratorDefinition->setBindings([MigrationContext::class => $contextDefinition]);
            $migrationDefinition->addMethodCall('add', [new Reference($migratorId)]);
        }

        // Before tasks
        if (null !== $migrationConfig['tasks']['before']) {
            foreach ($migrationConfig['tasks']['before'] as $beforeTaskClass) {
                $taskDefinition = new Definition($beforeTaskClass);
                $taskId = $migrationId . '.task.before.' . (new UnicodeString($beforeTaskClass))->snake();
                $container->setDefinition($taskId, $taskDefinition);

                $taskDefinition->setBindings([MigrationContext::class => $contextDefinition]);
                $migrationDefinition->addMethodCall('addBeforeTask', [new Reference($taskId)]);
            }
        }

        // After tasks
        if (null !== $migrationConfig['tasks']['after']) {
            foreach ($migrationConfig['tasks']['after'] as $afterTaskClass) {
                $taskDefinition = new Definition($afterTaskClass);
                $taskId = $migrationId . '.task.after.' . (new UnicodeString($afterTaskClass))->snake();
                $container->setDefinition($taskId, $taskDefinition);

                $taskDefinition->setBindings([MigrationContext::class => $contextDefinition]);
                $migrationDefinition->addMethodCall('addAfterTask', [new Reference($taskId)]);
            }
        }
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
