<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Model;

enum CustomerType: string
{
    case Enterprise = 'enterprise';
    case SME = 'sme';
}
