<?php

namespace Fregata\Migrator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Base class for many other migrators providing a minimal implementation
 */
abstract class AbstractMigrator implements MigratorInterface
{
    /**
     * @var array|null rows to insert into target database
     */
    private ?array $data = null;

    /**
     * Get the data in the source database
     *
     * @throws MigratorException
     */
    protected function fetchData(Connection $source): void
    {
        // Get SELECT query to fetch data from source
        $pullQuery = $source->createQueryBuilder();
        $pullQuery = $this->pullFromSource($pullQuery);

        if ($pullQuery->getType() !== QueryBuilder::SELECT) {
            throw MigratorException::wrongQueryType('SELECT', 'pull');
        }

        // Execute & fetch
        $data = $pullQuery->execute();
        $data = $data->fetchAll(FetchMode::ASSOCIATIVE);

        // Insert rows 1 by 1
        $insertedRows = 0;
        foreach ($data as $row) {
            // Get INSERT query to fetch data from source
            $pushQuery = $target->createQueryBuilder();
            $pushQuery = $this->pushToTarget($pushQuery, $row);

            if ($pushQuery->getType() !== QueryBuilder::INSERT) {
                throw MigratorException::wrongQueryType('INSERT', 'push');
            }

            $insertedRows += $pushQuery->execute();
            yield $insertedRows;
        }
    }

    /**
     * Create the SELECT query to get data from source
     */
    abstract protected function pullFromSource(QueryBuilder $queryBuilder): QueryBuilder;

    /**
     * Create the INSERT query to save data into target
     */
    abstract protected function pushToTarget(QueryBuilder $queryBuilder, array $row): QueryBuilder;
}