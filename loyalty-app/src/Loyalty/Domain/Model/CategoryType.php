<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Model;

enum CategoryType: string
{
    case Purchases = 'purchases';
    case Referrals = 'referrals';
    case Reviews = 'reviews';
}
