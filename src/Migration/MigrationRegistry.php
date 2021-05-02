<?php

namespace Fregata\Migration;

/**
 * The migration registry holds every migrations defined in the application
 */
class MigrationRegistry
{
    /** @var array<string, Migration> */
    private array $migrations = [];

    /**
     * Register a new migration
     * @throws MigrationException
     */
    public function add(string $name, Migration $migration): void
    {
        if (isset($this->migrations[$name])) {
            throw MigrationException::duplicateMigration($name);
        }
        $this->migrations[$name] = $migration;
    }

    /**
     * Fetch a single migration
     * @param string $name the migration identifier
     */
    public function get(string $name): ?Migration
    {
        return $this->migrations[$name] ?? null;
    }

    /**
     * Fetch all known migrations
     * @return array<string, Migration>
     */
    public function getAll(): array
    {
        return $this->migrations;
    }
}
