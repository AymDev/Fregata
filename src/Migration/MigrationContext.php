<?php

namespace Fregata\Migration;

/**
 * The migration context holds useful informations about the current migration
 */
final class MigrationContext
{
    private Migration $migration;
    private string $migrationName;
    private array $options;
    private ?string $parentName;

    public function __construct(
        Migration $migration,
        string $migrationName,
        ?array $options = null,
        ?string $parentName = null
    ) {
        $this->migration = $migration;
        $this->migrationName = $migrationName;
        $this->options = $options ?? [];
        $this->parentName = $parentName;
    }

    /**
     * Get the current migration object
     */
    public function getMigration(): Migration
    {
        return $this->migration;
    }

    /**
     * Get the migration name as written in the configuration
     */
    public function getMigrationName(): string
    {
        return $this->migrationName;
    }

    /**
     * Get options for the migration as defined in the configuration
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the parent migration name if set
     */
    public function getParentName(): ?string
    {
        return $this->parentName;
    }
}
