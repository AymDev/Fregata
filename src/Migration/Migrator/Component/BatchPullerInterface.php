<?php

namespace Fregata\Migration\Migrator\Component;

/**
 * A batch puller can help with large datasets by pulling the data part by part
 */
interface BatchPullerInterface extends PullerInterface
{
    /**
     * @inheritDoc
     * @return \Generator|array[] the data batch to process
     */
    public function pull(): \Generator;
}
