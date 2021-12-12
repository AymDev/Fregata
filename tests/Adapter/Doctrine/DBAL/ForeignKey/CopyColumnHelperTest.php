<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\ForeignKey;

use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use PHPUnit\Framework\TestCase;

class CopyColumnHelperTest extends TestCase
{
    /**
     * Every generated column/index name must have a framework related prefix
     */
    public function testPrefix(): void
    {
        $helper = new CopyColumnHelper();
        $prefix = '_fregata_';

        $localColumn = $helper->localColumn('table', 'column');
        self::assertStringStartsWith($prefix, $localColumn);

        $localIndex = $helper->localColumnIndex('table', 'column');
        self::assertStringStartsWith($prefix, $localIndex);

        $foreignColumn = $helper->foreignColumn('table', 'column');
        self::assertStringStartsWith($prefix, $foreignColumn);

        $foreignIndex = $helper->foreignColumnIndex('table', 'column');
        self::assertStringStartsWith($prefix, $foreignIndex);
    }

    /**
     * Generated column/index names must be unique
     */
    public function testGeneratedNamesAreUnique(): void
    {
        $helper = new CopyColumnHelper();

        $names = [
            $helper->localColumn('table', 'column'),
            $helper->localColumnIndex('table', 'column'),
            $helper->foreignColumn('table', 'column'),
            $helper->foreignColumnIndex('table', 'column'),
        ];

        self::assertSame(
            count($names),
            count(array_unique($names))
        );
    }
}
