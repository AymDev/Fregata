<?php

namespace Fregata\Migration;

/**
 * A task can be executed before or after a migration,
 * it can help setting up requirements before a migration, or cleaning after it
 */
interface TaskInterface
{
    /**
     * The task process
     * @return string|null an optional string to print when the task is executed
     */
    public function execute(): ?string;
}
