<?php


namespace Fregata\Migrator;


class MigratorException extends \Exception
{
    public static function wrongQueryType(string $expectedQuery, string $operation): self
    {
        return new self(sprintf(
            'The migrator must return a "%s" query during a %s operation.',
            $expectedQuery,
            $operation
        ));
    }

    public static function wrongMigrator(string $className): self
    {
        return new self(sprintf(
            'A migrator must implement "%s", instance of "%s" provided.',
            MigratorInterface::class,
            $className
        ));
    }
}