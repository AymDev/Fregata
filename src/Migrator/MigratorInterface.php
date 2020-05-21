<?php

namespace Fregata\Migrator;

use Doctrine\DBAL\Connection;

/**
 * Migrator Interface
 *
 * This is the interface to implement to create custom migrators.
 */
interface MigratorInterface
{
    /**
     * @return string the source connection class name
     */
    public function getSourceConnection(): string;

    /**
     * @return string the target connection class name
     */
    public function getTargetConnection(): string;

    /**
     * Execute the migration
     *
     * @return int the number of rows inserted into the target database
     */
    public function migrate(Connection $source, Connection $target): int;
}