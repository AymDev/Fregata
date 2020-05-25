<?php

namespace Fregata\Migrator;

/**
 * Interface used by migrators of tables which primary keys are referenced
 * as foreign keys in other tables and need to be preserved.
 *
 * Be aware this will create a temporary column in the target table
 */
interface PreservedKeyMigratorInterface
{
    /**
     * The name of the column to preserve.
     * The method is needed in case the pull operation is JOINing tables.
     * @return string column name in the pull operation query
     */
    public function getPrimaryKeyColumnName(): string;

    /**
     * @return string table name in the source database
     */
    public function getSourceTable(): string;

    /**
     * @return string table name in the target database
     */
    public function getTargetTable(): string;
}