<?php

namespace Fregata\Adapter\Doctrine\DBAL\ForeignKey;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;

/**
 * Represent a foreign key constraint to keep after a migration
 */
class ForeignKey
{
    private ForeignKeyConstraint $constraint;
    private string $tableName;

    /** @var string[] */
    private array $allowNull;

    /**
     * @param ForeignKeyConstraint $constraint the foreign key constraint in the target database
     * @param string               $tableName  the local table name
     *                                         Required as of a Doctrine bug which prevents Fregata from getting this
     *                                         information from the constraint object
     * @param string[]             $allowNull  local column names you need to drop NOT NULL during the migration,
     *                                         the columns will be set back to NOT NULL afterwards
     *                                         @see https://github.com/doctrine/dbal/issues/4506
     */
    public function __construct(ForeignKeyConstraint $constraint, string $tableName, array $allowNull = [])
    {
        $this->constraint = $constraint;
        $this->tableName = $tableName;
        $this->allowNull = $allowNull;
    }

    public function getConstraint(): ForeignKeyConstraint
    {
        return $this->constraint;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return string[]
     */
    public function getAllowNull(): array
    {
        return $this->allowNull;
    }
}
