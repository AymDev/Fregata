<?php

namespace Fregata\Tests\Console;

use Fregata\Console\MigrationListCommand;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MigrationListCommandTest extends TestCase
{
    /**
     * The migration:list command must display
     *  - number of migrations
     *  - name of each migration
     */
    public function testListRegisteredMigrations()
    {
        $registry = new MigrationRegistry();
        $registry->add('foo', new Migration());
        $registry->add('bar', new Migration());

        $command = new MigrationListCommand($registry);
        $tester = new CommandTester($command);

        $tester->execute([]);
        $display = $tester->getDisplay();

        // Number of migrations:
        $firstLine = strtok($display, "\n");
        self::assertStringEndsWith('2', $firstLine);

        // Migration names
        self::assertStringContainsString('foo', $display);
        self::assertStringContainsString('bar', $display);

        // No migrators table
        self::assertStringNotContainsString('#   Class name', $display);
    }

    /**
     * The --with-migrators option must list migrators
     */
    public function testListMigrationsWithMigrators()
    {
        $migration = new Migration();
        $migration->add(new MigrationListCommandFirstMigrator());
        $migration->add(new MigrationListCommandSecondMigrator());

        $registry = new MigrationRegistry();
        $registry->add('foo', $migration);

        $command = new MigrationListCommand($registry);
        $tester = new CommandTester($command);

        $tester->execute([
            '--with-migrators' => null,
        ]);
        $display = $tester->getDisplay();

        // Get table data lines
        $firstClass = preg_quote(MigrationListCommandFirstMigrator::class, '~');
        $secondClass = preg_quote(MigrationListCommandSecondMigrator::class, '~');
        self::assertMatchesRegularExpression(
            '~0\s+' . $firstClass . '\s+\R\s+1\s+' . $secondClass . '\s+\R~',
            $display
        );
    }
}

/**
 * Mocks
 * @see MigrationListCommandTest::testListMigrationsWithMigrators()
 */
class MigrationListCommandFirstMigrator implements MigratorInterface {
    public function getPuller(): ?PullerInterface {}
    public function getPusher(): PusherInterface {}
    public function getExecutor(): Executor {}
}

class MigrationListCommandSecondMigrator extends MigrationListCommandFirstMigrator {}
