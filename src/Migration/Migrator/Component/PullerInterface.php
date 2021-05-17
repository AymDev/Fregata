<?php

namespace Fregata\Migration\Migrator\Component;

/**
 * A puller is a component fetching data from a source
 */
interface PullerInterface
{
    /**
     * Pull data from the source
     * @return mixed
     */
    public function pull();

    /**
     * Return total number of items to migrate if applicable
     */
    public function count(): ?int;
}
