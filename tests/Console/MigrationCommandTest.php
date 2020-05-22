<?php

namespace Fregata\Tests\Console;

use Fregata\Console\MigrationCommand;
use Fregata\Tests\FregataTestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Symfony\Component\Console\Tester\CommandTester;

class MigrationCommandTest extends FregataTestCase
{
    /**
     * Use a configuration file
     */
    public function testConfigurationFile()
    {
        // Valid migrator referenced in configuration file
        $migrator = $this->getMigratorInterfaceConcretion();
        $migrator_class = get_class($migrator);

        // Configuration file
        $file = vfsStream::newFile('fregata-test.yml')->setContent(<<<YAML
            migrators:
                - $migrator_class
            YAML
        );
        $this->vfs->addChild($file);

        // Command test
        $command = new MigrationCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--configuration' => $file->url(),
        ]);

        self::assertStringContainsString('Configuration has been loaded for 1 migrators.', $commandTester->getDisplay());
        self::assertStringContainsString('Migrated successfully !', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * Use the migrator option
     */
    public function testMigratorOption()
    {
        $migrator_a = $this->getMigratorInterfaceConcretion();
        $migrator_b = $this->getMigratorInterfaceConcretion();

        // Command test
        $command = new MigrationCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--migrator' => [
                get_class($migrator_a),
                get_class($migrator_b),
            ],
        ]);

        self::assertStringContainsString('Configuration has been loaded for 2 migrators.', $commandTester->getDisplay());
        self::assertStringContainsString('Migrated successfully !', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * Use migrators-dir option
     */
    public function testMigratorsDirOption()
    {
        $file = $this->getMigratorInterfaceConcretionFile('MigratorsDirOption');
        $this->vfs->addChild($file);

        // Include file to avoid implementing an autoloader
        require $file->url();

        // Command test
        $command = new MigrationCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--migrators-dir' => $this->vfs->url(),
        ]);

        self::assertStringContainsString('Configuration has been loaded for 1 migrators.', $commandTester->getDisplay());
        self::assertStringContainsString('Migrated successfully !', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }
}