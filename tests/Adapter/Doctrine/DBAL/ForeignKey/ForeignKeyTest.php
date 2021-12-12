<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\ForeignKey;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKey;
use PHPUnit\Framework\TestCase;

class ForeignKeyTest extends TestCase
{
    /**
     * Basic usage
     */
    public function testGetters(): void
    {
        $constraint = new ForeignKeyConstraint(['local'], 'foreign_table', ['foreign']);
        $table = 'local_table';
        $allowNull = ['local'];

        $foreignKey = new ForeignKey($constraint, $table, $allowNull);

        self::assertSame($constraint, $foreignKey->getConstraint());
        self::assertSame($table, $foreignKey->getTableName());
        self::assertSame($allowNull, $foreignKey->getAllowNull());
    }
}
