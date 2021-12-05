<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\ForeignKey\Task;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKey;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Migrator\HasForeignKeysInterface;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyAfterTask;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyBeforeTask;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationContext;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Tests\Adapter\Doctrine\DBAL\AbstractDbalTestCase;

class ForeignKeyAfterTaskTest extends AbstractDbalTestCase
{
    protected function getTables(): array
    {
        return [
            'source_referenced',
            'source_referencing',
            'target_referenced',
            'target_referencing',
        ];
    }

    /**
     * Copy columns must be deleted after migration and modified columns must be reset to NOT NULL
     */
    public function testMigrationForeignKeys()
    {
        // Database setup
        $this->connection->getWrappedConnection()->exec(<<<SQL
            CREATE TABLE source_referenced (
                pk INT NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (pk)
            ) ENGINE=INNODB;

            CREATE TABLE source_referencing (
                fk INTEGER NOT NULL,
                FOREIGN KEY fk_ref (fk) REFERENCES source_referenced (pk)
            ) ENGINE=INNODB;

            INSERT INTO source_referenced VALUES (4), (5), (6);
            INSERT INTO source_referencing VALUES (6), (5), (5), (4);

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
        $migration->add(new FunctionalTestReferencingMigratorMock($this->connection, new CopyColumnHelper()));
        $migration->add(new FunctionalTestReferencedMigratorMock($this->connection, new CopyColumnHelper()));
        $context = new MigrationContext($migration, 'copy_columns');

        // Execute before task
        $task = new ForeignKeyBeforeTask($context, new CopyColumnHelper());
        $task->execute();

        foreach ($migration->process() as $migrator) {
            $generator = $migrator->getExecutor()->execute($migrator->getPuller(), $migrator->getPusher());
            while ($generator->valid()) {
                $generator->current();
                $generator->next();
            }
        }

        // Execute after task
        $task = new ForeignKeyAfterTask($context, new CopyColumnHelper());
        $task->execute();

        // Check referenced table
        $columns = $this->connection->getSchemaManager()->listTableColumns('target_referenced');
        self::assertCount(1, $columns);

        // Check referencing table
        $columns = $this->connection->getSchemaManager()->listTableColumns('target_referencing');
        self::assertCount(1, $columns);

        $originalColumn = $columns['fk'];
        self::assertTrue($originalColumn->getNotnull());

        // Check data
        $referencedData = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('target_referenced')
            ->execute()
            ->fetchAll(FetchMode::COLUMN);
        self::assertSame([1, 2, 3], array_map('intval', $referencedData));

        $referencingData = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('target_referencing')
            ->orderBy('fk', 'DESC')
            ->execute()
            ->fetchAll(FetchMode::COLUMN);
        self::assertSame([3, 2, 2, 1], array_map('intval', $referencingData));
    }
}
