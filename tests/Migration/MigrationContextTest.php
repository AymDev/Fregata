<?php

namespace Fregata\Tests\Migration;

use Fregata\Migration\Migration;
use Fregata\Migration\MigrationContext;
use PHPUnit\Framework\TestCase;

class MigrationContextTest extends TestCase
{
    /**
     * Testing basic usage
     */
    public function testGetters(): void
    {
        $migration = new Migration();
        $name = 'test-migration';
        $options = [
            'foo' => 'bar',
        ];
        $parent = 'parent-migration';

        $context = new MigrationContext($migration, $name, $options, $parent);

        self::assertInstanceOf(Migration::class, $context->getMigration());
        self::assertSame($name, $context->getMigrationName());
        self::assertSame($options, $context->getOptions());
        self::assertSame($parent, $context->getParentName());
    }
}
