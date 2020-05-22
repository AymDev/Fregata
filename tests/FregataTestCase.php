<?php

namespace Fregata\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Fregata\Connection\AbstractConnection;
use Fregata\Migrator\MigratorInterface;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\TestCase;

abstract class FregataTestCase extends TestCase
{
    /** @var vfsStreamDirectory Virtual file system */
    protected vfsStreamDirectory $vfs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vfs = vfsStream::setup('fregata-test');
    }

    /**
     * Get a MySQL connection
     */
    public function getMySQLConnection(): AbstractConnection
    {
        return new class extends AbstractConnection {
            public string $url = 'mysql://root:root@127.0.0.1:3306/fregata_source';
        };
    }

    /**
     * Get a Postgres connection
     */
    public function getPgSQLConnection(): AbstractConnection
    {
        return new class extends AbstractConnection {
            public string $url = 'pgsql://postgres:postgres@127.0.0.1:5432/fregata_target';
        };
    }

    /**
     * Create a table with a given dataset in the source database
     */
    public function createSourceTable(AbstractConnection $source, Table $table, array $dataset): void
    {
        $sourceConnection = $source->getConnection();
        $sourceConnection
            ->getSchemaManager()
            ->dropAndCreateTable($table);

        foreach ($dataset as $row) {
            $sourceConnection->insert($table->getName(), $row);
        }
    }

    /**
     * Create an empty table in the target database
     */
    public function createTargetTable(AbstractConnection $target, Table $table): void
    {
        $targetSchema = $target->getConnection()->getSchemaManager();
        $targetSchema->dropAndCreateTable($table);
    }

    /**
     * Create a valid migrator implementation
     */
    public function getMigratorInterfaceConcretion(): MigratorInterface
    {
        return new class implements MigratorInterface {
            public function getSourceConnection(): string
            {
                return get_class(new class extends AbstractConnection {
                    public string $url = 'mysql://root:root@127.0.0.1:3306/fregata_source';
                });
            }

            public function getTargetConnection(): string
            {
                return get_class(new class extends AbstractConnection {
                    public string $url = 'pgsql://postgres:postgres@127.0.0.1:5432/fregata_target';
                });
            }

            public function migrate(Connection $source, Connection $target): int
            {
                return 0;
            }
        };
    }

    /**
     * Returns a vfs file containing a valid migrator implementation
     */
    public function getMigratorInterfaceConcretionFile(string $classname): vfsStreamFile
    {
        return vfsStream::newFile(sprintf('%s.php', $classname))->setContent(<<<PHP_CLASS
            <?php
            namespace FregataTest;
            
            use Doctrine\DBAL\Connection;
            use Fregata\Connection\AbstractConnection;
            use Fregata\Migrator\MigratorInterface;
            
            class $classname implements MigratorInterface {
                public function getSourceConnection(): string
                {
                    return get_class(new class extends AbstractConnection {
                        public string \$url = 'mysql://root:root@127.0.0.1:3306/fregata_source';
                    });
                }
                
                public function getTargetConnection(): string
                {
                    return get_class(new class extends AbstractConnection {
                        public string \$url = 'pgsql://postgres:postgres@127.0.0.1:5432/fregata_target';
                    });
                }
                
                public function migrate(Connection \$source, Connection \$target): int
                {
                    return 0;
                }
            }
            PHP_CLASS
        );
    }
}