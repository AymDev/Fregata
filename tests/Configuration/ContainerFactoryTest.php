<?php

namespace Fregata\Tests\Configuration;

use Fregata\Configuration\ConfigurationException;
use Fregata\Configuration\ContainerFactory;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

class ContainerFactoryTest extends TestCase
{
    public function testContainerCreation()
    {
        $fileSystem = vfsStream::setup('kernel-factory-test', null, [
            'config' => [
                'fregata.yaml' => 'fregata:',
            ],
            'cache' => []
        ]);

        $factory = new ContainerFactory();
        $container = $factory->getContainer(
            $fileSystem->url() . '/config',
            $fileSystem->url() . '/cache',
        );

        // Dummy assertion
        $this->assertInstanceOf(Container::class, $container);
    }

    /**
     * An exception is thrown when an invalid configuration directory is given
     * @dataProvider provideInvalidConfigurationPaths
     */
    public function testContainerCreationWithInvalidPaths(int $exceptionCode, string $cachePath, ?string $configPath)
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionCode($exceptionCode);

        $factory = new ContainerFactory();
        $factory->getContainer($configPath, $cachePath);
    }

    public function provideInvalidConfigurationPaths(): array
    {
        return [
            // Cache directory does not exist
            [
                1619874486570,
                __DIR__ . '/does-not-exists',
                null
            ],
            // Cache directory is a file
            [
                1619874486570,
                __FILE__,
                null
            ],
            // Config directory does not exist
            [
                1619865822238,
                __DIR__,
                __DIR__ . '/does-not-exists'
            ],
            // Config directory is a file
            [
                1619865822238,
                __DIR__,
                __FILE__
            ],
        ];
    }
}
