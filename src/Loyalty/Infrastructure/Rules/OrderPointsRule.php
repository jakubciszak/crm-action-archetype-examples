<?php

declare(strict_types=1);

namespace Loyalty\Infrastructure\Rules;

use Loyalty\Domain\IncentiveAction;
use Loyalty\Domain\IncentiveDecision;
use Loyalty\Domain\IncentiveRule;
use Loyalty\Domain\JournalEntry;
use Loyalty\Domain\RewardGrant;

final readonly class OrderPointsRule implements IncentiveRule
{
    public function supports(string $actionType): bool
    {
        return $actionType === 'order_placed';
    }

    public function evaluate(IncentiveAction $action): IncentiveDecision
    {
        $amount = $action->payload()['totalAmountCents'];
        $points = intdiv($amount, 1000); // 1 punkt za 10 zł

        return new IncentiveDecision(
            journalEntries: [new JournalEntry(points: $points, reason: "Order {$action->payload()['orderId']}")],
            rewardGrants: $points >= 100 ? [new RewardGrant('free_shipping', 'Darmowa dostawa')] : [],
        );
    }
}
