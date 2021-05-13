<?php

namespace Fregata\Tests\Configuration;

use Fregata\Configuration\AbstractFregataKernel;
use Fregata\Configuration\FregataExtension;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationContext;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use Fregata\Tests\Configuration\Fixtures\ExtensionTestDirectoryMigrator;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FregataExtensionTest extends TestCase
{
    /**
     * Migration services must be defined
     */
    public function testMigrationServicesDefinitions()
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $extension->load([], $container);

        // Migration registry
        $registry = $container->get('fregata.migration_registry');
        self::assertInstanceOf(MigrationRegistry::class, $registry);

        // Base migration
        $migration = $container->get('fregata.migration');
        self::assertInstanceOf(Migration::class, $migration);
    }

    /**
     * Migration services must be defined
     */
    public function testMigrationDefinition()
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $configuration = [
            'migrations' => [
                'test_migration' => [
                    'migrators_directory' => __DIR__ . '/Fixtures',
                    'migrators' => [ExtensionTestMigrator::class],
                ]
            ]
        ];
        $extension->load([$configuration], $container);

        // Migration
        self::assertTrue($container->has('fregata.migration.test_migration'));

        // Migrators
        self::assertTrue($container->has('fregata.migration.test_migration.migrator.fregata_tests_configuration_fixtures_extension_test_directory_migrator'));
        self::assertTrue($container->has('fregata.migration.test_migration.migrator.fregata_tests_configuration_extension_test_migrator'));
    }

    /**
     * Before tasks must be defined
     */
    public function testBeforeTaskDefinitions()
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $configuration = [
            'migrations' => [
                'test_migration' => [
                    'tasks' => [
                        'before' => [
                            ExtensionTestTask::class,
                        ]
                    ]
                ]
            ]
        ];
        $extension->load([$configuration], $container);

        // Migration
        self::assertTrue($container->has('fregata.migration.test_migration'));

        // Task
        self::assertTrue($container->has('fregata.migration.test_migration.task.before.fregata_tests_configuration_extension_test_task'));
    }

    /**
     * After tasks must be defined
     */
    public function testAfterTaskDefinitions()
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $configuration = [
            'migrations' => [
                'test_migration' => [
                    'tasks' => [
                        'after' => [
                            ExtensionTestTask::class,
                        ]
                    ]
                ]
            ]
        ];
        $extension->load([$configuration], $container);

        // Migration
        self::assertTrue($container->has('fregata.migration.test_migration'));

        // Task
        self::assertTrue($container->has('fregata.migration.test_migration.task.after.fregata_tests_configuration_extension_test_task'));
    }

    /**
     * Context must be defined
     */
    public function testContextDefinitions()
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
    public function testContextIsAccurate()
    {
        $fileSystem = vfsStream::setup('fregata-extension-test', null, [
            'config' => [
                'fregata.yaml' => <<<YAML
                    fregata:
                        migrations:
                            test_migration:
                                options:
                                    foo: bar
                                migrators:
                                    - Fregata\Tests\Configuration\ExtensionTestMigrator
                            child_migration:
                                parent: test_migration
                    YAML,
            ],
            'cache' => []
        ]);

        // Create kernel
        $kernel = new class($fileSystem) extends AbstractFregataKernel {
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
        };

        $container = $kernel->getContainer();

        /** @var MigrationRegistry $registry */
        $registry = $container->get('fregata.migration_registry');
        self::assertInstanceOf(MigrationRegistry::class, $registry);

        $migration = $registry->get('child_migration');

        /** @var ExtensionTestMigrator $migrator */
        $migrator = $migration->getMigrators()[0];
        self::assertInstanceOf(ExtensionTestMigrator::class, $migrator);

        $context = $migrator->getContext();
        self::assertInstanceOf(MigrationContext::class, $context);
        self::assertSame($migration, $context->getMigration());
        self::assertSame(['foo' => 'bar'], $context->getOptions());
        self::assertSame('child_migration', $context->getMigrationName());
        self::assertSame('test_migration', $context->getParentName());
    }
}

/**
 * Mock
 * @see FregataExtensionTest::testMigrationDefinition
 */
class ExtensionTestMigrator implements MigratorInterface
{
    private MigrationContext $context;

    public function __construct(MigrationContext $context)
    {
        $this->context = $context;
    }

    public function getContext(): MigrationContext
    {
        return $this->context;
    }

    public function getPuller(): ?PullerInterface {}
    public function getPusher(): PusherInterface {}
    public function getExecutor(): Executor {}
}

/**
 * Mock
 * @see FregataExtensionTest::testBeforeTaskDefinitions
 * @see FregataExtensionTest::testAfterTaskDefinitions
 */
class ExtensionTestTask implements TaskInterface
{
    public function execute(): ?string {}
}
