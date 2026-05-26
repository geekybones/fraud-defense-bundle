<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class FraudDefenseBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
