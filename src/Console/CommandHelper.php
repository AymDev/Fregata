<?php

namespace Fregata\Console;

use Symfony\Component\Console\Style\SymfonyStyle;

class CommandHelper
{
    /**
     * Display an object list table
     * @param object[] $objects
     */
    public function printObjectTable(SymfonyStyle $io, array $objects, string $columnName = 'Class name'): void
    {
        $objects = array_map(
            fn(int $key, object $obj) => [$key, get_class($obj)],
            array_keys($objects),
            $objects
        );

        $io->table(
            ['#', $columnName],
            $objects
        );
    }
}
