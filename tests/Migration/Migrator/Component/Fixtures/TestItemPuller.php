<?php

namespace Fregata\Tests\Migration\Migrator\Component\Fixtures;

/**
 * @internal for testing purposes only.
 */
final class TestItemPuller implements FixturePullerInterface
{
    /** @var string[] */
    private array $items = [
        'foo',
        'bar',
        'baz',
    ];

    /**
     * @return string[]
     */
    public function pull(): array
    {
        return $this->items;
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
