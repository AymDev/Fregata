<?php

namespace Fregata\Connection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Base class for any database connection
 *
 * Child classes must declare properties as connection parameters
 * that will be passed to \Doctrine\DBAL\DriverManager::getConnection()
 */
abstract class AbstractConnection
{
    /**
     * Get the database connection
     */
    public function getConnection(): Connection
    {
        $params = get_object_vars($this);
        return DriverManager::getConnection($params);
    }
}