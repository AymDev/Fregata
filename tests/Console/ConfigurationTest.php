<?php


namespace Fregata\Tests\Console;


use Fregata\Console\Configuration;
use Fregata\Tests\FregataTestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class ConfigurationTest extends FregataTestCase
{
    /**
     * The migrators key returns unmodified configuration value
     */
    public function testMigratorsKey()
    {
        $config = new Configuration([
            'migrators' => ['SomeClass']
        ]);

        $migrators = $config->getMigrators();
        self::assertSame(['SomeClass'], $migrators);
    }

    /**
     * The migrators_directory key returns concrete class names implementing MigratorInterface
     */
    public function testMigratorsDirectoryKey()
    {
        // A PHP class which IS NOT a migrator
        $genericClassFile = vfsStream::newFile('NotAMigrator.php')->setContent(<<<'PHP_CLASS'
            <?php
            namespace ConfigurationTest;
            
            class NotAMigrator {}
            PHP_CLASS
        );

        // A PHP class which IS a migrator BUT is abstract
        $abstractMigratorClassFile = vfsStream::newFile('MyAbstractMigrator.php')->setContent(<<<'PHP_CLASS'
            <?php
            namespace ConfigurationTest;
            use Fregata\Migrator\MigratorInterface;
            
            abstract class MyAbstractMigrator implements MigratorInterface { }
            PHP_CLASS
        );

        // A PHP class which IS a migrator
        $migratorClassFile = $this->getMigratorInterfaceConcretionFile('MigratorsDirectoryKey');

        $this->vfs->addChild($genericClassFile);
        $this->vfs->addChild($migratorClassFile);
        $this->vfs->addChild($abstractMigratorClassFile);

        // Include the files to avoid implementing an autoloader
        require $genericClassFile->url();
        require $abstractMigratorClassFile->url();
        require $migratorClassFile->url();

        $config = new Configuration([
            'migrators_directory' => $this->vfs->url()
        ]);

        $migrators = $config->getMigrators();
        self::assertSame(['FregataTest\MigratorsDirectoryKey'], $migrators);
    }
}