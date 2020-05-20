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
     */
    public function migrate(Connection $source, Connection $target): void;
}