<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\State;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Completed = 'completed';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Draft => [self::Active],
            self::Active => [self::Suspended, self::Completed],
            self::Suspended => [self::Active, self::Completed],
            self::Completed => [],
        }, true);
    }
}
