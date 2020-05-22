<?php


namespace Fregata\Tests\Console;


use Fregata\Console\Configuration;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    private vfsStreamDirectory $vfs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vfs = vfsStream::setup('fregata-test');
    }

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

        // A PHP class which IS a migrator
        $migratorClassFile = vfsStream::newFile('MyMigrator.php')->setContent(<<<'PHP_CLASS'
            <?php
            namespace ConfigurationTest;
            use Doctrine\DBAL\Connection;
            use Fregata\Migrator\MigratorInterface;
            
            class MyMigrator implements MigratorInterface {
                public function getSourceConnection(): string
                {
                    return '';
                }
                
                public function getTargetConnection(): string
                {
                    return '';
                }
                
                public function migrate(Connection $source, Connection $target): int
                {
                    return 0;
                }
            }
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
        $this->vfs->addChild($genericClassFile);
        $this->vfs->addChild($migratorClassFile);
        $this->vfs->addChild($abstractMigratorClassFile);

        // Include the files to avoid implementing an autoloader
        require $genericClassFile->url();
        require $migratorClassFile->url();
        require $abstractMigratorClassFile->url();

        $config = new Configuration([
            'migrators_directory' => $this->vfs->url()
        ]);

        $migrators = $config->getMigrators();
        self::assertSame(['ConfigurationTest\MyMigrator'], $migrators);
    }
}