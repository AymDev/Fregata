<?php

declare(strict_types=1);

namespace Fregata;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class FregataBundle extends Bundle
{

    const NAME = 'Fregata';

    public function __construct()
    {
        $this->name = self::NAME;
    }
}
