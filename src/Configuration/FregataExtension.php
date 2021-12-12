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

class FregataExtension extends Extension
{
    /** @phpstan-var migrationConfig[] */
    private array $configuration;

    /**
     * @param mixed[] $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
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

    protected function createServiceDefinitions(ContainerBuilder $container): void
    {
        // Base migration service
        $container
            ->setDefinition('fregata.migration', new Definition(Migration::class))
            ->setPublic(false)
        ;
    }

    /**
     * @phpstan-param migrationConfig $migrationConfig
     */
    protected function registerMigration(ContainerBuilder $container, array $migrationConfig): void
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
            $migrationConfig['parent'] ?? null,
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
            $taskDefinition->setAutowired(true);
            $container->setDefinition($taskId, $taskDefinition);

            $taskDefinition->setBindings([MigrationContext::class => new BoundArgument($contextDefinition, false)]);
            $migrationDefinition->addMethodCall('addBeforeTask', [new Reference($taskId)]);
        }

        // After tasks
        foreach ($this->findAfterTaskForMigration($migrationConfig) as $afterTaskClass) {
            $taskDefinition = new Definition($afterTaskClass);
            $taskId = $migrationId . '.task.after.' . (new UnicodeString($afterTaskClass))->snake();
            $taskDefinition->setAutowired(true);
            $container->setDefinition($taskId, $taskDefinition);

            $taskDefinition->setBindings([MigrationContext::class => new BoundArgument($contextDefinition, false)]);
            $migrationDefinition->addMethodCall('addAfterTask', [new Reference($taskId)]);
        }
    }

    /**
     * @phpstan-param migrationConfig $migrationConfig
     * @return mixed[]
     */
    protected function findOptionsForMigration(array $migrationConfig): array
    {
        $options = [];

        // Migration has a parent
        if (isset($migrationConfig['parent'])) {
            $parent = $migrationConfig['parent'];
            $options = $this->findOptionsForMigration($this->configuration[$parent]);
        }

        // Migration has an options list
        return array_merge($options, $migrationConfig['options'] ?? []);
    }

    /**
     * @phpstan-param migrationConfig $migrationConfig
     * @return class-string[]
     */
    protected function findBeforeTaskForMigration(array $migrationConfig): array
    {
        $tasks = [];

        // Migration has a parent
        if (isset($migrationConfig['parent'])) {
            $parent = $migrationConfig['parent'];
            $tasks = $this->findBeforeTaskForMigration($this->configuration[$parent]);
        }

        // Migration has a task list
        return array_merge($tasks, $migrationConfig['tasks']['before'] ?? []);
    }

    /**
     * @phpstan-param migrationConfig $migrationConfig
     * @return class-string[]
     */
    protected function findAfterTaskForMigration(array $migrationConfig): array
    {
        $tasks = [];

        // Migration has a parent
        if (isset($migrationConfig['parent'])) {
            $parent = $migrationConfig['parent'];
            $tasks = $this->findAfterTaskForMigration($this->configuration[$parent]);
        }

        // Migration has a task list
        return array_merge($tasks, $migrationConfig['tasks']['after'] ?? []);
    }

    /**
     * @param mixed[] $migrationConfig
     * @phpstan-param migrationConfig $migrationConfig
     * @return class-string[]
     */
    protected function findMigratorsForMigration(array $migrationConfig): array
    {
        $migrators = [];

        // Migration has a parent
        if (isset($migrationConfig['parent'])) {
            $parent = $migrationConfig['parent'];
            $migrators = $this->findMigratorsForMigration($this->configuration[$parent]);
        }

        // Migration has a migrator directory
        if (isset($migrationConfig['migrators_directory'])) {
            $dirMigrators = $this->findMigratorsInDirectory($migrationConfig['migrators_directory']);
            $migrators = array_merge($migrators, $dirMigrators);
        }

        // Migration has a migrator list
        return array_merge($migrators, $migrationConfig['migrators'] ?? []);
    }

    /**
     * @return class-string[]
     */
    protected function findMigratorsInDirectory(string $path): array
    {
        $finder = new Finder();
        $iterator = new ClassIterator($finder->in($path));
        $iterator->enableAutoloading();

        /** @var ClassIterator<MigratorInterface> $iterator */
        $iterator = $iterator->type(MigratorInterface::class);
        $iterator = $iterator->where('isInstantiable', true);

        $classes = [];

        /** @var \ReflectionClass<MigratorInterface> $class */
        foreach ($iterator as $class) {
            $classes[] = $class->getName();
        }

        return $classes;
    }
}
