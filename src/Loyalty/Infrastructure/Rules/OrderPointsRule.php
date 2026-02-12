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
        $payload = $action->payload();
        $orderId = $payload['orderId'];
        $lines = $payload['lines'] ?? [];

        $entries = [];
        $totalPoints = 0;

        foreach ($lines as $line) {
            $linePoints = intdiv($line['amountCents'], 1000); // 1 pkt per 10 zł
            $totalPoints += $linePoints;
            $entries[] = new JournalEntry(
                points: $linePoints,
                reason: "Order {$orderId}, line {$line['lineId']}",
                sourceRef: $orderId,
                sourceItemRef: $line['lineId'],
                label: $line['productName'] ?? null,
            );
        }

        return new IncentiveDecision(
            journalEntries: $entries,
            rewardGrants: $totalPoints >= 100
                ? [new RewardGrant('free_shipping', 'Darmowa dostawa')]
                : [],
        );
    }
}
