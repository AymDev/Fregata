# Fregata - PHP database migrator

![](https://github.com/AymDev/Fregata/workflows/Unit%20Test%20Suite/badge.svg)

**Fregata** is a framework allowing data migration between different DBMS or database structures.
It runs with **Doctrine**'s [DBAL](https://www.doctrine-project.org/projects/dbal.html).

**Documentation**:

1. [Concepts](#concepts)
    1. [Connections](#connections)
    2. [Migrators](#migrators)
        1. [Creating custom migrators](#creating-custom-migrators)
        2. [Using AbstractMigrator](#using-abstractmigrator)
2. [Usage](#usage)
    1. [Installation](#installation)
    2. [Using Fregata](#using-fregata)

# Concepts
**Fregata** uses 2 types of component you need to build based on provided abstraction.

## Connections
*Connections* are value objects representing a connection to a single database. 
It must extend `AbstractConnection` and have properties to build the database connection.
```php
<?php

use Fregata\Connection\AbstractConnection;

class MyConnection extends AbstractConnection {
    public string $driver = 'pdo_mysql';
    public string $host = 'localhost';
    public string $user = 'user';
    public string $password = 'secret';
    public string $dbname = 'mydb';
}
```
The property names can be any connection parameter you would send to Doctrine 
(see [DBAL - Configuration](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/configuration.html#configuration)).

You should have at least 2 *connections*:

 - a **source**: database to get data from
 - a **target**: database to save data into

## Migrators
A *migrator* is a component which defines how to migrate data from a **source** to a **target**.
Any *migrator* must define its **source** connection, and its **target** connection.

### Creating custom migrators
If the provided *migrators* don't fit your needs, 
the only requirement to create your own is to implement `MigratorInterface` with the following methods:

 - `getSourceConnection()`: return source connection class name
 - `getTargetConnection()`: return target connection class name
 - `migrate()`: executes the migration

### Using AbstractMigrator
This is the only provided *migrator* at the moment. 
By extending it you will need to provide a `SELECT` query and a `INSERT` query
using **Doctrine**'s [SQL Query Builder](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/query-builder.html#sql-query-builder).

**Example implementation:**
```php
<?php
use Doctrine\DBAL\Query\QueryBuilder;
use Fregata\Migrator\AbstractMigrator;

class MyMigrator extends AbstractMigrator
{
    /**
     * Define the source connection to use for this migrator
     */
    public function getSourceConnection() : string
    {
        return MySourceConnection::class;
    }

    /**
     * Define the target connection to use for this migrator
     */
    public function getTargetConnection() : string
    {
        return MyTargetConnection::class;
    }

    /**
     * Build the SELECT query to get the data to migrate
     */
    protected function pullFromSource(QueryBuilder $queryBuilder) : QueryBuilder
    {
        return $queryBuilder
            ->select('title, desc')
            ->from('article');
    }

    /**
     * Build the INSERT query to save a single row into the target
     */
    protected function pushToTarget(QueryBuilder $queryBuilder, array $row) : QueryBuilder
    {
        return $queryBuilder
            ->insert('product')
            ->setValue('title', '?')
            ->setValue('description', '?')
            ->setParameter(0, $row['title'])
            ->setParameter(1, $row['desc'])
    }
}
```

# Usage

## Installation
You can install it with **Composer:**
```shell
composer require aymdev/fregata
```
**Fregata** requires **PHP** >= 7.4.

## Using Fregata
There is no vendor binary provided yet. 
A migration can be executed using the main `Fregata` class:
```php
<?php

use Fregata\Fregata;

require_once __DIR__ . '/vendor/autoload.php';

$fregata = new Fregata();

// Add a migrator
$fregata->addMigrator(new MyMigrator());

// Start the migration
$fregata->run();
```