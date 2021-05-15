<?php

namespace Fregata\Adapter\Doctrine\DBAL\ForeignKey\Migrator;

use Doctrine\DBAL\Connection;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKey;
use Fregata\Migration\Migrator\MigratorInterface;

/**
 * This interface should be implemented by migrators of tables having foreign keys constraints to keep
 * If used, add the corresponding before and after tasks.
 */
interface HasForeignKeysInterface extends MigratorInterface
{
    /**
     * Return the database connection to the target database
     * It should be the connection used in the pusher
     */
    public function getConnection(): Connection;

    /**
     * List the foreign keys constraints to keep
     * @return ForeignKey[]
     */
    public function getForeignKeys(): array;
}
