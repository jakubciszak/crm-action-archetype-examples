<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Model;

enum DecisionType: string
{
    case PointsGranted = 'points_granted';
    case RewardGrant = 'reward_grant';
    case Rejected = 'rejected';
}
