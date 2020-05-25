<?php

namespace Fregata\Migrator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Fregata\Connection\AbstractConnection;

/**
 * Base class for many other migrators providing a minimal implementation
 */
abstract class AbstractMigrator implements MigratorInterface
{
    /**
     * @var array[] rows to insert into target database
     */
    protected array $data;

    /**
     * @var int number of rows already inserted
     */
    protected int $insertCount = 0;

    /**
     * @var int current batch number for pull operation
     */
    protected int $pullOffset = 0;

    /**
     * How many rows should be fetched from source at once, or all rows if null (default)
     * @return int|null number of rows
     */
    protected function getPullBatchSize(): ?int
    {
        return null;
    }

    /**
     * Gets the pull query and validates it
     *
     * @throws MigratorException
     */
    protected function getPullQuery(Connection $source): QueryBuilder
    {
        $queryBuilder = $source->createQueryBuilder();
        $pullQuery = $this->pullFromSource($queryBuilder);

        // must be a SELECT statement
        if ($pullQuery->getType() !== QueryBuilder::SELECT) {
            throw MigratorException::wrongQueryType('SELECT', 'pull');
        }

        return $pullQuery;
    }

    /**
     * Count the result rows of the SELECT statement
     */
    public function getTotalRows(Connection $source): int
    {
        // Get user query
        $pullQuery = $this
            ->getPullQuery($source)
            ->getSQL();

        // Build query to count total rows
        $query = $source->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(sprintf('(%s)', $pullQuery), 'user_query');

        /** @var ResultStatement $result */
        $result = $query->execute();
        return $result->fetchColumn();
    }

    public function migrate(Connection $source, Connection $target): \Generator
    {
        do {
            $remainingRows = $this->pullBatch($source);

            // Insert rows 1 by 1
            foreach ($this->data as $row) {
                // Get INSERT query to fetch data from source
                $pushQuery = $target->createQueryBuilder();
                $pushQuery = $this->pushToTarget($pushQuery, $row);

                if ($pushQuery->getType() !== QueryBuilder::INSERT) {
                    throw MigratorException::wrongQueryType('INSERT', 'push');
                }

                $this->insertCount += $pushQuery->execute();
                yield $this->insertCount;
            }
        } while ($remainingRows === true);
    }

    /**
     * Executes the pull operation query to fetch specific amount of rows from source
     *
     * @return bool whether there is still data to fetch or not
     */
    protected function pullBatch(Connection $source): bool
    {
        // Build query with LIMIT clause
        $pullQuery = $this->getPullQuery($source);
        $batchSize = $this->getPullBatchSize();

        if ($batchSize !== null && $batchSize > 0) {
            $pullQuery
                ->setFirstResult($batchSize * $this->pullOffset)
                ->setMaxResults($batchSize);

            $this->pullOffset++;
        } else {
            // No LIMIT clause (all rows fetched at once): do not reexecute the method
            $remainingRows = false;
        }

        $data = $pullQuery->execute();
        $this->data = $data->fetchAll(FetchMode::ASSOCIATIVE);
        return $remainingRows ?? $this->data !== [];
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