<?php

namespace Fregata\Migrator;

use Doctrine\DBAL\Connection;
use Fregata\Connection\AbstractConnection;

/**
 * Migrator Interface
 *
 * This is the interface to implement to create custom migrators.
 */
interface MigratorInterface
{
    /**
     * @return AbstractConnection the connection to the source database
     */
    public function getSourceConnection(): AbstractConnection;

    /**
     * @return AbstractConnection the connection to the target database
     */
    public function getTargetConnection(): AbstractConnection;

    /**
     * Execute the migration
     *
     * @return \Generator which yields the number of rows inserted into the target database
     */
    public function migrate(Connection $source, Connection $target): \Generator;
}