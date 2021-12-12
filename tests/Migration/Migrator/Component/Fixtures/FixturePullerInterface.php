<?php

namespace Fregata\Tests\Migration\Migrator\Component\Fixtures;

use Fregata\Migration\Migrator\Component\PullerInterface;

/**
 * @internal for testing purposes only.
 */
interface FixturePullerInterface extends PullerInterface
{
    /**
     * @return string[]
     */
    public function getItems(): array;
}
