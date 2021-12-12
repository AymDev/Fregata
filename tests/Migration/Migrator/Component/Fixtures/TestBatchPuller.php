<?php

namespace Fregata\Tests\Migration\Migrator\Component\Fixtures;

use Fregata\Migration\Migrator\Component\BatchPullerInterface;

/**
 * @internal for testing purposes only.
 */
final class TestBatchPuller implements FixturePullerInterface, BatchPullerInterface
{
    /** @var string[] */
    private array $items = [
        'boom',
        'cow',
        'milk',
    ];

    /**
     * @return \Generator<string[]>|string[][]
     */
    public function pull(): \Generator
    {
        foreach ($this->items as $item) {
            yield [$item];
        }
    }

    public function count(): ?int
    {
        return count($this->items);
    }

    /**
     * @return string[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
