<?php

namespace Fregata\Migration\Migrator\Component;

/**
 * A pusher is a component inserting data into a target
 */
interface PusherInterface
{
    /**
     * Insert data into target
     * @return int number of items inserted at once
     */
    public function push($data): int;
}
