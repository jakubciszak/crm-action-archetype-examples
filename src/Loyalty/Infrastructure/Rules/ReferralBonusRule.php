<?php

declare(strict_types=1);

namespace Loyalty\Infrastructure\Rules;

use Loyalty\Domain\IncentiveAction;
use Loyalty\Domain\IncentiveDecision;
use Loyalty\Domain\IncentiveRule;
use Loyalty\Domain\JournalEntry;

final readonly class ReferralBonusRule implements IncentiveRule
{
    public function supports(string $actionType): bool
    {
        return $actionType === 'referral';
    }

    public function evaluate(IncentiveAction $action): IncentiveDecision
    {
        return new IncentiveDecision(
            journalEntries: [new JournalEntry(points: 50, reason: "Referral by {$action->participantId()}")],
            rewardGrants: [],
        );
    }
}
