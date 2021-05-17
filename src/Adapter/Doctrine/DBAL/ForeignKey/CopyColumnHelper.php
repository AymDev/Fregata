<?php

namespace Fregata\Adapter\Doctrine\DBAL\ForeignKey;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;

/**
 * This service can help you get the temporary "copy column" names created to keep foreign keys during a migration
 */
class CopyColumnHelper
{
    /**
     * Get name of a referenced column
     * For example: a primary key column
     * @param string $tableName  name of the referenced table
     * @param string $columnName name of the referenced column
     * @return string            name of the generated copy column
     */
    public function foreignColumn(string $tableName, string $columnName): string
    {
        return sprintf('_fregata_referenced_%s_%s', $tableName, $columnName);
    }

    /**
     * Get name of a referenced column index
     * @internal
     * @param string $tableName  name of the referenced table
     * @param string $columnName name of the referenced column
     * @return string            name of the generated index
     */
    public function foreignColumnIndex(string $tableName, string $columnName): string
    {
        return sprintf('_fregata_referenced_idx_%s_%s', $tableName, $columnName);
    }

    /**
     * Get name of a referencing column of a foreign key constraint
     * @param string $tableName  name of the referencing table
     * @param string $columnName name of the referencing column
     * @return string            name of the generated copy column
     */
    public function localColumn(string $tableName, string $columnName): string
    {
        return sprintf('_fregata_referencing_%s_%s', $tableName, $columnName);
    }

    /**
     * Get name of a referencing column index
     * @internal
     * @param string $tableName  name of the referencing table
     * @param string $columnName name of the referencing column
     * @return string            name of the generated index
     */
    public function localColumnIndex(string $tableName, string $columnName): string
    {
        return sprintf('_fregata_referencing_idx_%s_%s', $tableName, $columnName);
    }
}
