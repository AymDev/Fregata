<?php

namespace Fregata\Tests\Configuration;

use Fregata\Configuration\AbstractFregataKernel;
use Fregata\Configuration\FregataExtension;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationContext;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\String\UnicodeString;

class FregataExtensionTest extends TestCase
{
    /**
     * Migration services must be defined
     */
    public function testMigrationServicesDefinitions(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $extension->load([], $container);

        // Base migration
        $migration = $container->get('fregata.migration');
        self::assertInstanceOf(Migration::class, $migration);
    }

    /**
     * Migration services must be defined
     */
    public function testMigrationDefinition(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $migrator = self::getMockForAbstractClass(MigratorInterface::class);
        $migratorClass = get_class($migrator);

        $configuration = [
            'migrations' => [
                'test_migration' => [
                    'migrators_directory' => __DIR__ . '/Fixtures',
                    'migrators' => [
                        $migratorClass
                    ],
                ]
            ]
        ];
        $extension->load([$configuration], $container);

        // Migration
        $testMigrationId = 'fregata.migration.test_migration';
        self::assertTrue($container->has($testMigrationId));

        // Migrators
        $firstMigratorId = $testMigrationId;
        $firstMigratorId .= '.migrator.fregata_tests_configuration_fixtures_extension_test_directory_migrator';
        self::assertTrue($container->has($firstMigratorId));

        $secondMigratorId = sprintf(
            '%s.migrator.%s',
            $testMigrationId,
            (new UnicodeString($migratorClass))->snake()
        );
        self::assertTrue($container->has($secondMigratorId));

        // Migrators have autowiring
        $migratorDefinition = $container->getDefinition($secondMigratorId);
        self::assertTrue($migratorDefinition->isAutowired());
    }

    /**
     * Before tasks must be defined
     */
    public function testBeforeTaskDefinitions(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $task = self::getMockForAbstractClass(TaskInterface::class);
        $taskClass = get_class($task);

        $configuration = [
            'migrations' => [
                'test_migration' => [
                    'tasks' => [
                        'before' => [
                            $taskClass,
                        ]
                    ]
                ]
            ]
        ];
        $extension->load([$configuration], $container);

        // Migration
        $testMigrationId = 'fregata.migration.test_migration';
        self::assertTrue($container->has($testMigrationId));

        // Task
        $taskId = sprintf(
            '%s.task.before.%s',
            $testMigrationId,
            (new UnicodeString($taskClass))->snake()
        );
        self::assertTrue($container->has($taskId));

        // Before tasks have autowiring
        $taskDefinition = $container->getDefinition($taskId);
        self::assertTrue($taskDefinition->isAutowired());
    }

    /**
     * After tasks must be defined
     */
    public function testAfterTaskDefinitions(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $task = self::getMockForAbstractClass(TaskInterface::class);
        $taskClass = get_class($task);

        $configuration = [
            'migrations' => [
                'test_migration' => [
                    'tasks' => [
                        'after' => [
                            $taskClass,
                        ]
                    ]
                ]
            ]
        ];
        $extension->load([$configuration], $container);

        // Migration
        $testMigrationId = 'fregata.migration.test_migration';
        self::assertTrue($container->has($testMigrationId));

        // Task
        $taskId = sprintf(
            '%s.task.after.%s',
            $testMigrationId,
            (new UnicodeString($taskClass))->snake()
        );
        self::assertTrue($container->has($taskId));

        // After tasks have autowiring
        $taskDefinition = $container->getDefinition($taskId);
        self::assertTrue($taskDefinition->isAutowired());
    }

    /**
     * Context must be defined
     */
    public function testContextDefinitions(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $configuration = [
            'migrations' => [
                'test_migration' => []
            ]
        ];
        $extension->load([$configuration], $container);

        // Context
        self::assertTrue($container->has('fregata.migration.test_migration.context'));
    }

    /**
     * Context must be defined according to the migration
     */
    public function testContextIsAccurate(): void
    {
        $migrator = self::getMockForAbstractClass(MigratorInterface::class);
        $migratorClass = get_class($migrator);
        $taskClass = TestTask::class;

        $fileSystem = vfsStream::setup('fregata-extension-test', null, [
            'config' => [
                'fregata.yaml' => <<<YAML
                    fregata:
                        migrations:
                            test_migration:
                                options:
                                    foo: bar
                                migrators:
                                    - ${migratorClass}
                                tasks:
                                    before:
                                        - ${taskClass}
                            child_migration:
                                parent: test_migration
                    YAML,
            ],
            'cache' => []
        ]);

        // Create kernel
        $kernel = new class ($fileSystem) extends AbstractFregataKernel {
            private vfsStreamDirectory $vfs;

            public function __construct(vfsStreamDirectory $vfs)
            {
                $this->vfs = $vfs;
            }

            protected function getConfigurationDirectory(): string
            {
                return $this->vfs->url() . '/config';
            }

            protected function getCacheDirectory(): string
            {
                return $this->vfs->url() . '/cache';
            }

            protected function getContainerClassName(): string
            {
                return parent::getContainerClassName() . 'FregataExtensionTest';
            }
        };

        $container = $kernel->getContainer();

        /** @var MigrationRegistry $registry */
        $registry = $container->get('fregata.migration_registry');
        self::assertInstanceOf(MigrationRegistry::class, $registry);

        /** @var Migration $migration */
        $migration = $registry->get('child_migration');

        $migrator = $migration->getMigrators()[0];
        self::assertInstanceOf($migratorClass, $migrator);

        /** @var TestTask $task */
        $task = $migration->getBeforeTasks()[0];
        $context = $task->getContext();

        self::assertInstanceOf(MigrationContext::class, $context);
        self::assertSame($migration, $context->getMigration());
        self::assertSame(['foo' => 'bar'], $context->getOptions());
        self::assertSame('child_migration', $context->getMigrationName());
        self::assertSame('test_migration', $context->getParentName());
    }

    /**
     * Extension must tag migration services
     */
    public function testServicesAreTagged(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $migrator = self::getMockForAbstractClass(MigratorInterface::class);
        $migratorClass = get_class($migrator);
        $taskClass = TestTask::class;

        $configuration = [
            'migrations' => [
                'test_migration' => [
                    'migrators' => [
                        $migratorClass
                    ],
                    'tasks' => [
                        'before' => [
                            $taskClass,
                        ],
                        'after' => [
                            $taskClass,
                        ],
                    ]
                ]
            ]
        ];
        $extension->load([$configuration], $container);

        $migrations = $container->findTaggedServiceIds(FregataExtension::TAG_MIGRATION);
        self::assertCount(1, $migrations);

        $migrators = $container->findTaggedServiceIds(FregataExtension::TAG_MIGRATOR);
        self::assertCount(1, $migrators);

        $tasks = $container->findTaggedServiceIds(FregataExtension::TAG_TASK);
        self::assertCount(2, $tasks);

        $beforeTasks = $container->findTaggedServiceIds(FregataExtension::TAG_TASK_BEFORE);
        self::assertCount(1, $beforeTasks);

        $afterTasks = $container->findTaggedServiceIds(FregataExtension::TAG_TASK_AFTER);
        self::assertCount(1, $afterTasks);
    }
}

/**
 * Mock
 * @see FregataExtensionTest::testContextIsAccurate()
 */
class TestTask implements TaskInterface
{
    private MigrationContext $context;

    public function __construct(MigrationContext $context)
    {
        $this->context = $context;
    }

    public function execute(): ?string
    {
        return null;
    }

    public function getContext(): MigrationContext
    {
        return $this->context;
    }
}
