<?php

namespace Fregata\Tests\Configuration;

use Fregata\Configuration\FregataExtension;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Tests\Configuration\Fixtures\ExtensionTestDirectoryMigrator;
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
        self::assertTrue($container->has(ExtensionTestDirectoryMigrator::class));
        self::assertTrue($container->has(ExtensionTestMigrator::class));
    }
}

/**
 * Mock
 * @see FregataExtensionTest::testMigrationDefinition
 */
class ExtensionTestMigrator implements MigratorInterface
{
    public function getPuller(): ?PullerInterface {}
    public function getPusher(): PusherInterface {}
    public function getExecutor(): Executor {}
}