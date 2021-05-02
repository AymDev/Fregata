<?php

namespace Fregata\Migration\Migrator\Component;

/**
 * The executor processes a migrator components (puller and pusher)
 * This class is the most basic executor supporting batch pulling and can be extended for specific needs.
 */
class Executor
{
    /**
     * Executes the migration process
     * @param PullerInterface|null $puller component fetching the data, optional if the migrator has no source
     * @param PusherInterface      $pusher component inserting the data
     * @return \Generator|int[] current number of items migrated
     */
    public function execute(?PullerInterface $puller, PusherInterface $pusher): \Generator
    {
        // Pull by batch
        if ($puller instanceof BatchPullerInterface) {
            foreach ($puller->pull() as $batch) {
                foreach ($batch as $item) {
                    yield $pusher->push($item);
                }
            }
            return;
        }

        // Default migration (item by item)
        foreach ($puller->pull() as $item) {
            yield $pusher->push($item);
        }
    }
}
