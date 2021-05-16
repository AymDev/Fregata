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
    private array $configuration;

    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        
        $this->createServiceDefinitions($container);

        // Save complete configuration for access to migrations referenced as parent
        $this->configuration = $config['migrations'];
        array_walk($this->configuration, fn (&$migrationConfig, $key) => $migrationConfig['name'] = $key);

        foreach ($this->configuration as $config) {
            $this->registerMigration($container, $config);
        }
    }

    private function createServiceDefinitions(ContainerBuilder $container): void
    {
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
        $migrationDefinition->addTag('fregata.migration', ['name' => $migrationConfig['name']]);
        $container->setDefinition($migrationId, $migrationDefinition);

        // Migration context
        $contextDefinition = new Definition(MigrationContext::class);
        $contextDefinition->setArguments([
            new Reference($migrationId),
            $migrationConfig['name'],
            $this->findOptionsForMigration($migrationConfig),
            $migrationConfig['parent'],
        ]);
        $contextId = $migrationId . '.context';
        $container->setDefinition($contextId, $contextDefinition);

        // Migrator definitions
        foreach ($this->findMigratorsForMigration($migrationConfig) as $migratorClass) {
            $migratorDefinition = new Definition($migratorClass);
            $migratorId = $migrationId . '.migrator.' . (new UnicodeString($migratorClass))->snake();
            $migratorDefinition->setAutowired(true);
            $container->setDefinition($migratorId, $migratorDefinition);

            $migratorDefinition->setBindings([MigrationContext::class => new BoundArgument($contextDefinition, false)]);
            $migrationDefinition->addMethodCall('add', [new Reference($migratorId)]);
        }

        // Before tasks
        foreach ($this->findBeforeTaskForMigration($migrationConfig) as $beforeTaskClass) {
            $taskDefinition = new Definition($beforeTaskClass);
            $taskId = $migrationId . '.task.before.' . (new UnicodeString($beforeTaskClass))->snake();
            $container->setDefinition($taskId, $taskDefinition);

            $taskDefinition->setBindings([MigrationContext::class => new BoundArgument($contextDefinition, false)]);
            $migrationDefinition->addMethodCall('addBeforeTask', [new Reference($taskId)]);
        }

        // After tasks
        foreach ($this->findAfterTaskForMigration($migrationConfig) as $afterTaskClass) {
            $taskDefinition = new Definition($afterTaskClass);
            $taskId = $migrationId . '.task.after.' . (new UnicodeString($afterTaskClass))->snake();
            $container->setDefinition($taskId, $taskDefinition);

            $taskDefinition->setBindings([MigrationContext::class => new BoundArgument($contextDefinition, false)]);
            $migrationDefinition->addMethodCall('addAfterTask', [new Reference($taskId)]);
        }
    }

    private function findOptionsForMigration(array $migrationConfig): array
    {
        $options = [];

        // Migration has a parent
        if (null !== $migrationConfig['parent']) {
            $parent = $migrationConfig['parent'];
            $options = $this->findOptionsForMigration($this->configuration[$parent]);
        }

        // Migration has an options list
        return array_merge($options, $migrationConfig['options'] ?? []);
    }

    private function findBeforeTaskForMigration(array $migrationConfig): array
    {
        $tasks = [];

        // Migration has a parent
        if (null !== $migrationConfig['parent']) {
            $parent = $migrationConfig['parent'];
            $tasks = $this->findBeforeTaskForMigration($this->configuration[$parent]);
        }

        // Migration has a task list
        return array_merge($tasks, $migrationConfig['tasks']['before'] ?? []);
    }

    private function findAfterTaskForMigration(array $migrationConfig): array
    {
        $tasks = [];

        // Migration has a parent
        if (null !== $migrationConfig['parent']) {
            $parent = $migrationConfig['parent'];
            $tasks = $this->findAfterTaskForMigration($this->configuration[$parent]);
        }

        // Migration has a task list
        return array_merge($tasks, $migrationConfig['tasks']['after'] ?? []);
    }

    private function findMigratorsForMigration(array $migrationConfig): array
    {
        $migrators = [];

        // Migration has a parent
        if (null !== $migrationConfig['parent']) {
            $parent = $migrationConfig['parent'];
            $migrators = $this->findMigratorsForMigration($this->configuration[$parent]);
        }

        // Migration has a migrator directory
        if (null !== $migrationConfig['migrators_directory']) {
            $dirMigrators = $this->findMigratorsInDirectory($migrationConfig['migrators_directory']);
            $migrators = array_merge($migrators, $dirMigrators);
        }

        // Migration has a migrator list
        return array_merge($migrators, $migrationConfig['migrators'] ?? []);
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
