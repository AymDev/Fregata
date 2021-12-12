<?php

namespace Fregata\Tests\Migration\Migrator\Component\Fixtures;

use Fregata\Migration\Migrator\Component\PusherInterface;

/**
 * @internal for testing purposes only.
 */
final class TestPusher implements PusherInterface
{
    /** @var string[] */
    private array $data = [];

    /**
     * @param string $data
     */
    public function push($data): int
    {
        $this->data[] = $data;
        return 1;
    }

    /**
     * @return string[]
     */
    public function getData(): array
    {
        return $this->data;
    }
}
