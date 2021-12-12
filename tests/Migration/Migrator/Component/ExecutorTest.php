<?php

namespace Fregata\Tests\Migration\Migrator\Component;

use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Tests\Migration\Migrator\Component\Fixtures\FixturePullerInterface;
use Fregata\Tests\Migration\Migrator\Component\Fixtures\TestBatchPuller;
use Fregata\Tests\Migration\Migrator\Component\Fixtures\TestItemPuller;
use Fregata\Tests\Migration\Migrator\Component\Fixtures\TestPusher;
use PHPUnit\Framework\TestCase;

class ExecutorTest extends TestCase
{
    /**
     * @dataProvider providePuller
     */
    public function testItemMigration(FixturePullerInterface $puller): void
    {
        $pusher = new TestPusher();
        $executor = new Executor();

        $inserts = 0;
        foreach ($executor->execute($puller, $pusher) as $itemInserted) {
            $inserts += $itemInserted;
        }

        self::assertSame($puller->count(), $inserts);
        self::assertSame($puller->getItems(), $pusher->getData());
    }

    /**
     * @return FixturePullerInterface[][]
     */
    public function providePuller(): array
    {
        return [
            [new TestItemPuller()],
            [new TestBatchPuller()],
        ];
    }
}
