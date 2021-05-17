<?php

namespace Fregata\Tests\Migration;

use Fregata\Migration\Migration;
use Fregata\Migration\MigrationException;
use Fregata\Migration\MigrationRegistry;
use PHPUnit\Framework\TestCase;

class MigrationRegistryTest extends TestCase
{
    /**
     * Checks registry basic usage
     */
    public function testMigrationRegistryBasicUsage()
    {
        $registry = new MigrationRegistry();

        self::assertIsArray($registry->getAll());
        self::assertCount(0, $registry->getAll());

        $registry->add('first', new Migration());
        self::assertCount(1, $registry->getAll());

        $migration = $registry->get('first');
        self::assertInstanceOf(Migration::class, $migration);

        self::assertNull($registry->get('unknown'));
    }

    /**
     * Migration names must be unique
     */
    public function testCannotRegisterDuplicateMigrations()
    {
        self::expectException(MigrationException::class);
        self::expectExceptionCode(1619880941371);

        $registry = new MigrationRegistry();

        $registry->add('duplicate', new Migration());
        $registry->add('duplicate', new Migration());
    }
}
