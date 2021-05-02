<?php

namespace Fregata\Tests\Migration\Migrator\Component;

use Fregata\Migration\Migrator\Component\BatchPullerInterface;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use PHPUnit\Framework\TestCase;

class ExecutorTest extends TestCase
{
    /**
     * @dataProvider providePuller
     */
    public function testItemMigration(PullerInterface $puller)
    {
        $pusher = new Pusher();
        $executor = new Executor();

        $inserts = 0;
        foreach ($executor->execute($puller, $pusher) as $itemInserted) {
            $inserts += $itemInserted;
        }

        self::assertSame($puller->count(), $inserts);
        self::assertSame($puller->items, $pusher->data);
    }

    public function providePuller(): array
    {
        return [
            [new ItemPuller()],
            [new BatchPuller()],
        ];
    }
}

/**
 * Mocks
 */
class ItemPuller implements PullerInterface
{
    public array $items = [
        'foo',
        'bar',
        'baz',
    ];

    public function pull()
    {
        return $this->items;
    }

    public function count(): ?int
    {
        return count($this->items);
    }
}

class BatchPuller implements BatchPullerInterface
{
    public array $items = [
        'boom',
        'cow',
        'milk',
    ];

    public function pull(): \Generator
    {
        foreach($this->items as $item) {
            yield [$item];
        }
    }

    public function count(): ?int
    {
        return count($this->items);
    }
}

class Pusher implements PusherInterface
{
    public array $data = [];

    public function push($data): int
    {
        $this->data[] = $data;
        return 1;
    }
}
