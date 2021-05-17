# Fregata - PHP database migrator

![](https://github.com/AymDev/Fregata/workflows/Unit%20Test%20Suite/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/aymdev/fregata/v)](//packagist.org/packages/aymdev/fregata)
[![License](https://poser.pugx.org/aymdev/fregata/license)](//packagist.org/packages/aymdev/fregata)

**Fregata** is a data migration framework. You can use it to migrate any kind of data, but it has features to help you
migrate between different DBMS or database structures.

**Documentation**:

1. [Introduction](#introduction)
2. [Setup](#setup)
    1. [Installation](#installation)
    2. [Configuration](#configuration)
        1. [Kernel and service container](#kernel-and-service-container)
        2. [YAML configuration](#yaml-configuration)
3. [Components](#components)
    1. [Migration Registry](#migration-registry)
    2. [Migration](#migration)
        1. [Options](#options)
        2. [Parent migration](#parent-migration)
    3. [Task](#task)
    4. [Migrator](#migrator)
        1. [Puller](#puller)
        2. [Pusher](#pusher)
        3. [Executor](#executor)
4. [Tools](#tools)
    1. [Migration Context](#migration-context)
5. [Features](#features)
    1. [Dependent migrators](#dependent-migrators)
    2. [Batch pulling](#batch-pulling)
    3. [Foreign Key migrations](#foreign-key-migrations)
6. [CLI usage](#cli-usage)
    1. [List migrations](#list-migrations)
    2. [Get details of a migration](#get-details-of-a-migration)
    3. [Execute a migration](#execute-a-migration)
7. [Contributing](#contributing)

# Introduction
**Fregata** is a data migration framework. It can probably be compared to an *ETL (Extract Transform - Load)* tool.

You can use it to migrate data from files, databases, or anything you want, it is completely agnostic on this part
(some of its test migrate data between PHP arrays). But note that it was initially targeting databases, providing a way
to migrate data between different DBMS, even with a different structure. Some included features are specifically built
for databases.

>Why creating a framework for data migration ?

While database migrations might not be your everyday task, I encountered it multiple times on different projects. That's
why I created **Fregata** to have a migration workflow I could reuse.

>What are the use cases ?

Here are some example use cases (from experience):

 - when you want to change from a DBMS to another
 - when you want to sync your staging database with the production one (useful for CMS-based projects)


# Setup

## Installation

Install with Composer:
```shell
composer require aymdev/fregata
```

## Configuration

**Fregata** expects you to have a `config` and a `cache` directory at your project root by default.

### Kernel and service container

If you need to use a different directory structure than the default one, you can extend the
`Fregata\Configuration\AbstractFregataKernel` class.
Then you will have to implement methods to specify your configuration and cache directory.
>**Important**: your kernel full qualified class name ***must*** be `App\FregataKernel`.

The *kernel* holds a *service container*, built from **Symfony**'s **DependencyInjection** component.
This means you can define your own services as you would do it in a **Symfony** application, in a
`services.yaml` file in your configuration directory.

Here's a recommended minimal **services.yaml** to start your project:
```yaml
services:
    _defaults:
        autowire: true

    App\:
        resource: '../src/'
```

### YAML configuration

To configure **Fregata** itself, you will need a `fregata.yaml` file in your configuration directory.

Example configuration file:
```yaml
fregata:
    migrations:
        # define any name for your migration
        main_migration:
            # define custom options for your migrations
            options:
                custom_opt: 'opt_value'
                special_cfg:
                    foo: bar
            # load migrators from a directory
            # use the %fregata.root_dir% parameter to define a relative path from the project root
            migrators_directory: '%fregata.root_dir%/src/MainMigration'
            # load individual migrators
            # can be combined with migrators_directory
            migrators:
                - App\MainMigration\FirstMigrator
            # load tasks to execute before or after the migrators
            tasks:
                before:
                    - App\MainMigration\BeforeTask
                after:
                    - App\MainMigration\AfterTask
            
        other_migration:
            # extend an other migration to inherit its options, tasks and migrators
            parent: main_migration
            # overwrite a part of the options
            options:
                custom_opt: 'another_value'
            # load additional migrators or tasks
            migrators:
                - App\OtherMigration\Migrator
```

# Components

## Migration Registry

The **migration registry** contains every defined migrations. You shouldn't have to interact with it.

## Migration

A **migration** project holds the steps of a migration. For example, data migration from your production
database to staging one.
Each **migration** is created and saved into the registry based on your configuration. You don't need to
instantiate migration objects by yourself.

Migrations contain **tasks** and **migrators**. When a migration is run, components are executed in the
following order:

 - before tasks
 - migrators
 - after tasks

### Options

You may need to set specific configuration to your migration project, which could be used by **tasks**
or **migrators**.
With the `options` key you can define your migration specific configuration, they will be accessible to
the components from the [migration context](#migration-context). 

### Parent migration

When having multiple **migrations** for different environments, you probably want to avoid duplicating your
whole configuration.
You can extend a migration with the `parent` key. The *"child"* migration will inherit the parent's
*options*, **tasks** and **migrators**. You can still add more tasks and migrators, and overwrite options.

## Task

A **task** can be executed *before* or *after* **migrators**. They can be useful to bootstrap your migration
(before tasks) or to clean temporary data at the end (after tasks):

```php
use Fregata\Migration\TaskInterface;

class MyTask implements TaskInterface
{
    public function execute() : ?string
    {
        // perform some verifications, delete temporary data, ...
        return 'Optional result message';
    }
}
```

## Migrator

The **migrators** are the main components of the framework. A single migrator holds 3 components:

 - a **puller**
 - a **pusher**
 - an **executor**

It must return its components from getter methods by implementing
`Fregata\Migration\Migrator\MigratorInterface`.
A **migrator** represents the migration of a data from a **source** to a **target**. For example, migrating data
from a *MySQL* table to a *PostgreSQL* one.

### Puller

A **puller** is a **migrator** component responsible for *pulling data from a source*. It returns data
and optionally the number of items to migrate:

```php
use Doctrine\DBAL\Connection;
use Fregata\Migration\Migrator\Component\PullerInterface;

class Puller implements PullerInterface
{
    private Connection $connection;
    
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function pull()
    {
        return $this->connection
            ->executeQuery('SELECT * FROM my_table')
            ->fetchAllAssociative();
    }
    
    public function count() : ?int
    {
        return $this->connection
            ->executeQuery('SELECT COUNT(*) FROM my_table')
            ->fetchColumn();
    }
}
```

### Pusher

A **pusher** gets item fetched by the **puller** 1 by 1 and has to *push the data to a target*:

```php
use Doctrine\DBAL\Connection;
use Fregata\Migration\Migrator\Component\PusherInterface;

class Pusher implements PusherInterface
{
    private Connection $connection;
    
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * @return int number of items inserted
     */
    public function push($data): int
    {
        return $this->connection->executeStatement(
            'INSERT INTO my_table VALUES (:foo, :bar, :baz)',
            [
                'foo' => $data['foo'],
                'bar' => some_function($data['bar']),
                'baz' => 'default value',
            ]
        );
    }
}
```
Here `$data` is a single item from the example **puller** returned value. The `push()` method is called
multiple times.
The separation of **pullers** and **pushers** allow you to migrate between different sources: pull from
a file and push to a database, etc.

### Executor

The **executor** is the component which plugs a **puller** with a **pusher**. A default one is provided
and should work for most cases: `Fregata\Migration\Migrator\Component\Executor`.
Extend the default **executor** if you need a specific behaviour.

# Tools

## Migration Context

You can get some informations about the current **migration** by injecting the 
`Fregata\Migration\MigrationContext` service in a **task** or **migration**.

It provides:

 - current **migration** object
 - current migration **name**
 - migration **options**
 - **parent** migration name if applicable

# Features

## Dependent migrators

If your **migrators** need to be executed in a specific order you can define dependencies, and they will
be sorted automatically:

```php
use Fregata\Migration\Migrator\DependentMigratorInterface;

class DependentMigrator implements DependentMigratorInterface
{
    public function getDependencies() : array
    {
        return [
            DependencyMigrator::class,
        ];
    }
    
    // other migrator methods ...
}
```
Here, `DependencyMigrator` will be executed before `DependentMigrator`.

## Batch pulling

When a **puller** works with very large datasets you might want to pull the data by chunks:

```php
use Doctrine\DBAL\Connection;
use Fregata\Migration\Migrator\Component\BatchPullerInterface;

class BatchPulling implements BatchPullerInterface
{
    private Connection $connection;
    private ?int $count = null;
    
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function pull(): \Generator
    {
        $limit = 50;
        $offset = 0;
        
        while ($offset < $this->count()) {
            yield $this->connection
                ->executeQuery(sprintf('SELECT * FROM my_table LIMIT %d, %d', $offset, $limit))
                ->fetchAllAssociative();
            
            $offset += $limit;
        }
    }
    
    public function count() : ?int
    {
        if (null === $this->count) {
            $this->count = $this->connection
                ->executeQuery('SELECT COUNT(*) FROM my_table')
                ->fetchColumn();
        }
        
        return $this->count;
    }
}
```

## Foreign Key migrations

One of the most complex parts of a database migration is about **foreign keys**. There are multiple steps
to follow to perform a valid foreign key migration. This is done using **Doctrine DBAL**.

You must add 2 tasks to your migration:

 - **before** task: `Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyBeforeTask`
 - **after** task: `Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyAfterTask`

The before task will create temporary columns in your target database to keep the original referenced and
referencing columns. It may also change referencing columns to allow `NULL` (only if you specify it).
The after task will set the real values in your original referencing columns and then drop the temporary
columns.

Then the migrators must provide the database connection and the list of foreign keys:

```php
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKey;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Migrator\HasForeignKeysInterface;

class ReferencingMigrator implements HasForeignKeysInterface
{
    private Connection $connection;
    
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    
    public function getConnection() : Connection
    {
        return $this->connection;
    }
    
    /**
     * List the foreign keys constraints to keep
     * @return ForeignKey[]
     */
    public function getForeignKeys() : array
    {
        $constraints = $this->connection->getSchemaManager()->listTableForeignKeys('my_table');
        return array_map(
            function (ForeignKeyConstraint $constraint) {
                return new ForeignKey(
                    $constraint,            // DBAL constraint object
                    'target_referencing',   // name of the referencing table
                    ['fk']                  // columns to change to allow NULL (will be set back to NOT NULL in the after task)
                );
            },
            $constraints
        );
    }
    
    // other migrator methods ...
}
```

The migrators are responsible for the data migration, this means you need to fill the temporary columns
with original primary/foreign key from the source database.
To get the name of a temporary column, require the `CopyColumnHelper` service in your **pusher**:

```php
use Doctrine\DBAL\Connection;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Migration\Migrator\Component\PusherInterface;

class ReferencingForeignKeyPusher implements PusherInterface
{
    private Connection $connection;
    private CopyColumnHelper $columnHelper;
    
    public function __construct(Connection $connection, CopyColumnHelper $columnHelper)
    {
        $this->connection = $connection;
        $this->columnHelper = $columnHelper;
    }
    
    /**
     * @return int number of items inserted
     */
    public function push($data): int
    {
        return $this->connection->executeStatement(
            sprintf(
                'INSERT INTO my_table (column, %s) VALUES (:value, :old_fk)',
                $this->columnHelper->localColumn('my_table', 'fk_column')
            ),
            [
                'value' => $data['value'],
                'old_fk' => $data['fk_column'],
            ]
        );
    }
}
```
This example show the *local* (or *referencing*) side but this need to be done for the *foreign* (or 
*referenced*) side too, using `CopyColumnHelper::foreignColumn()`.

# CLI usage

**Fregata** provides a simple program to run the migrations, you can launch it with:
```shell
php vendor/bin/fregata
```

## List migrations

To list the migrations of your installation, run the `migration:list` command:
```shell
> php vendor/bin/fregata migration:list

Registered migrations: 2
========================

main_migration
other_migration

```

## Get details of a migration

To get information about a single migration, run the `migration:show` command:
```shell
> php vendor/bin/fregata migration:show main_migration

main_migration : 1 migrators
============================

 --- --------------------------------- 
  #   Migrator Name                   
 --- --------------------------------- 
  0   App\MainMigration\FirstMigrator 
 --- --------------------------------- 

```

## Execute a migration

And the most important one to run a migration: `migration:execute`.
```shell
> php vendor/bin/fregata migration:execute main_migration

 Confirm execution of the "main_migration" migration ? (yes/no) [no]:
 > yes

                                                                                                                        
 [OK] Starting "main_migration" migration: 1 migrators                                                                  
                                                                                                                        

Before tasks: 1
===============

 App\MainMigration\BeforeTask : OK

Migrators: 1
============

0 - Executing "App\MainMigration\FirstMigrator" [3 items] :
===========================================================

 3/3 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%


After tasks: 1
==============

 App\MainMigration\AfterTask : OK

                                                                                                                        
 [OK] Migrated successfully !                                                                                           

```

# Contributing

A **Docker** setup is available, run `make start` to start the services and `make shell` to open the command
line inside the **PHP** container.

If you want to test the implementation of the framework (using a **Composer**
[path repository](https://getcomposer.org/doc/05-repositories.md#path)), install it in a `_implementation`
directory at the root of the project, it is ignored by Git by default and will ensure you are using your
implementation autoloader.
