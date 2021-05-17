<?php

namespace Fregata\Migration;

use Fregata\Migration\Migrator\MigratorInterface;
use MJS\TopSort\CircularDependencyException;
use MJS\TopSort\ElementNotFoundException;

class MigrationException extends \Exception
{
    /**
     * Thrown during an attempt to register a migration with an already registered name
     */
    public static function duplicateMigration(string $name, \Throwable $previous = null): self
    {
        return new self(
            sprintf('A migration has already been registered with the name "%s".', $name),
            1619880941371,
            $previous
        );
    }

    /**
     * Thrown during an attempt to register a migrator multiple times in the same migration
     */
    public static function duplicateMigrator(MigratorInterface $migrator, \Throwable $previous = null): self
    {
        return new self(
            sprintf('A "%s" migrator has already been registered.', get_class($migrator)),
            1619907353293,
            $previous
        );
    }

    /**
     * Thrown if an error occurs during migrators dependency sorting
     * @param CircularDependencyException|ElementNotFoundException $previous
     */
    public static function invalidMigratorDependencies(\Exception $previous): self
    {
        return new self(
            sprintf('Dependent migrator: %s.', $previous->getMessage()),
            1619911058924,
            $previous
        );
    }
}
