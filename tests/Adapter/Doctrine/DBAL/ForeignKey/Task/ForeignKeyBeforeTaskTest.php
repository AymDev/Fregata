<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\ForeignKey\Task;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKey;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKeyException;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Migrator\HasForeignKeysInterface;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyBeforeTask;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationContext;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Tests\Adapter\Doctrine\DBAL\AbstractDbalTestCase;

class ForeignKeyBeforeTaskTest extends AbstractDbalTestCase
{
    protected function getTables(): array
    {
        return [
            'target_referenced',
            'target_referencing',
        ];
    }

    /**
     * Migrators with foreign keys get copy columns for referenced and referencing columns
     */
    public function testCopyKeyColumns()
    {
        // Database setup
        $this->connection->getWrappedConnection()->exec(<<<SQL
            CREATE TABLE target_referenced (
                pk INT NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (pk)
            ) ENGINE=INNODB;

            CREATE TABLE target_referencing (
                fk INTEGER NOT NULL,
                FOREIGN KEY fk_ref (fk) REFERENCES target_referenced (pk)
            ) ENGINE=INNODB;
        SQL);

        // Setup task
        $migration = new Migration();
        $migration->add(new ForeignKeyBeforeTaskMigrator($this->connection));
        $context = new MigrationContext($migration, 'copy_columns');

        // Execute task
        $task = new ForeignKeyBeforeTask($context, new CopyColumnHelper());
        $task->execute();

        // Check referenced table
        $columns = $this->connection->getSchemaManager()->listTableColumns('target_referenced');
        self::assertCount(2, $columns);

        $tempColumn = array_values($columns)[1];
        self::assertStringStartsWith('_fregata', $tempColumn->getName());
        self::assertFalse($tempColumn->getAutoincrement());
        self::assertFalse($tempColumn->getNotnull());
        self::assertNull($tempColumn->getDefault());

        // Check referencing table
        $columns = $this->connection->getSchemaManager()->listTableColumns('target_referencing');
        self::assertCount(2, $columns);

        $originalColumn = $columns['fk'];
        self::assertFalse($originalColumn->getNotnull());

        $tempColumn = array_values($columns)[1];
        self::assertStringStartsWith('_fregata', $tempColumn->getName());
        self::assertFalse($tempColumn->getNotnull());
        self::assertNull($tempColumn->getDefault());
    }

    /**
     * SQLite is an incompatible platform as it does not support foreign key constraints
     */
    public function testIncompatiblePlatform()
    {
        self::expectException(ForeignKeyException::class);
        self::expectExceptionCode(1621088365786);

        // Setup task
        $migration = new Migration();
        $migration->add(new class implements HasForeignKeysInterface {

            public function getConnection(): Connection
            {
                return DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
            }

            public function getForeignKeys(): array
            {
            }
            public function getPuller(): PullerInterface
            {
            }
            public function getPusher(): PusherInterface
            {
            }
            public function getExecutor(): Executor
            {
            }
        });
        $context = new MigrationContext($migration, 'incompatible');

        // Execute task
        $task = new ForeignKeyBeforeTask($context, new CopyColumnHelper());
        $task->execute();
    }
}

/**
 * Mock
 * @see ForeignKeyBeforeTaskTest::testCopyKeyColumns()
 */
class ForeignKeyBeforeTaskMigrator implements HasForeignKeysInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getForeignKeys(): array
    {
        return array_map(
            fn (ForeignKeyConstraint $constraint) => new ForeignKey($constraint, 'target_referencing', ['fk']),
            $this->connection->getSchemaManager()->listTableForeignKeys('target_referencing')
        );
    }

    public function getPuller(): PullerInterface
    {
    }
    public function getPusher(): PusherInterface
    {
    }
    public function getExecutor(): Executor
    {
    }
}
