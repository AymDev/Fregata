<?php

namespace Fregata\Tests\Configuration;

use Fregata\Configuration\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

class ConfigurationTest extends TestCase
{
    /**
     * @param mixed[] $parsedYamlConfig
     * @dataProvider provideConfiguration
     */
    public function testConfigurationFormat(array $parsedYamlConfig): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $processedConfiguration = $processor->processConfiguration($configuration, $parsedYamlConfig);

        self::assertArrayHasKey('migrations', $processedConfiguration);
        self::assertIsArray($processedConfiguration['migrations']);

        foreach ($processedConfiguration['migrations'] as $migration) {
            if (array_key_exists('options', $migration)) {
                self::assertIsArray($migration['options']);
            }

            if (array_key_exists('migrators_directory', $migration)) {
                self::assertIsString($migration['migrators_directory']);
            }

            if (array_key_exists('parent', $migration)) {
                self::assertIsString($migration['parent']);
            }

            if (array_key_exists('migrators', $migration)) {
                self::assertIsArray($migration['migrators']);
                self::assertContainsOnly('string', $migration['migrators']);
            }

            if (array_key_exists('tasks', $migration)) {
                self::assertIsArray($migration['tasks']);

                if (array_key_exists('before', $migration['tasks'])) {
                    self::assertIsArray($migration['tasks']['before']);
                    self::assertContainsOnly('string', $migration['tasks']['before']);
                }

                if (array_key_exists('after', $migration['tasks'])) {
                    self::assertIsArray($migration['tasks']['after']);
                    self::assertContainsOnly('string', $migration['tasks']['after']);
                }
            }
        }
    }

    /**
     * @return \Generator<mixed[]>|mixed[][]
     */
    public function provideConfiguration(): \Generator
    {
        /** @var string[] $configFiles */
        $configFiles = glob(__DIR__ . '/Fixtures/configuration_*.yaml');

        foreach ($configFiles as $file) {
            /** @var string $content */
            $content = file_get_contents($file);

            yield [Yaml::parse($content)];
        }
    }
}
