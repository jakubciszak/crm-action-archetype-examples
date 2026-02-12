<?php

declare(strict_types=1);

namespace Tests\Loyalty;

use Loyalty\Domain\IncentiveAction;
use Loyalty\Infrastructure\Rules\OrderPointsRule;
use Loyalty\Infrastructure\Rules\ReferralBonusRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IncentiveRuleTest extends TestCase
{
    #[Test]
    public function order_points_rule_supports_order_placed(): void
    {
        $rule = new OrderPointsRule();

        self::assertTrue($rule->supports('order_placed'));
        self::assertFalse($rule->supports('referral'));
    }

    #[Test]
    public function order_points_rule_creates_entry_per_line(): void
    {
        $rule = new OrderPointsRule();
        $action = new IncentiveAction(
            id: 'ACT-001',
            actionType: 'order_placed',
            payload: [
                'orderId' => 'ORD-001',
                'lines' => [
                    ['lineId' => 'L1', 'productName' => 'Product A', 'amountCents' => 15000],
                    ['lineId' => 'L2', 'productName' => 'Product B', 'amountCents' => 10000],
                ],
            ],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );

        $decision = $rule->evaluate($action);

        self::assertCount(2, $decision->journalEntries);
        self::assertSame(15, $decision->journalEntries[0]->points);
        self::assertSame('L1', $decision->journalEntries[0]->sourceItemRef);
        self::assertSame('Product A', $decision->journalEntries[0]->label);
        self::assertSame(10, $decision->journalEntries[1]->points);
        self::assertSame('L2', $decision->journalEntries[1]->sourceItemRef);
        self::assertCount(0, $decision->rewardGrants);
    }

    #[Test]
    public function order_points_rule_grants_reward_when_total_over_100(): void
    {
        $rule = new OrderPointsRule();
        $action = new IncentiveAction(
            id: 'ACT-002',
            actionType: 'order_placed',
            payload: [
                'orderId' => 'ORD-002',
                'lines' => [
                    ['lineId' => 'L1', 'productName' => 'Expensive', 'amountCents' => 150000],
                ],
            ],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );

        $decision = $rule->evaluate($action);

        self::assertSame(150, $decision->journalEntries[0]->points);
        self::assertCount(1, $decision->rewardGrants);
        self::assertSame('free_shipping', $decision->rewardGrants[0]->rewardId);
    }

    #[Test]
    public function order_points_rule_no_reward_below_100_total(): void
    {
        $rule = new OrderPointsRule();
        $action = new IncentiveAction(
            id: 'ACT-003',
            actionType: 'order_placed',
            payload: [
                'orderId' => 'ORD-003',
                'lines' => [
                    ['lineId' => 'L1', 'productName' => 'Cheap', 'amountCents' => 50000],
                    ['lineId' => 'L2', 'productName' => 'Also cheap', 'amountCents' => 49000],
                ],
            ],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );

        $decision = $rule->evaluate($action);

        $totalPts = array_sum(array_map(fn($e) => $e->points, $decision->journalEntries));
        self::assertSame(99, $totalPts);
        self::assertCount(0, $decision->rewardGrants);
    }

    #[Test]
    public function order_points_rule_stores_source_ref_on_entries(): void
    {
        $rule = new OrderPointsRule();
        $action = new IncentiveAction(
            id: 'ACT-004',
            actionType: 'order_placed',
            payload: [
                'orderId' => 'ORD-004',
                'lines' => [
                    ['lineId' => 'L1', 'productName' => 'Widget', 'amountCents' => 20000],
                ],
            ],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );

        $decision = $rule->evaluate($action);

        self::assertSame('ORD-004', $decision->journalEntries[0]->sourceRef);
        self::assertSame('L1', $decision->journalEntries[0]->sourceItemRef);
        self::assertSame('Widget', $decision->journalEntries[0]->label);
    }

    #[Test]
    public function referral_bonus_rule_supports_referral(): void
    {
        $rule = new ReferralBonusRule();

        self::assertTrue($rule->supports('referral'));
        self::assertFalse($rule->supports('order_placed'));
    }

    #[Test]
    public function referral_bonus_rule_grants_50_points(): void
    {
        $rule = new ReferralBonusRule();
        $action = new IncentiveAction(
            id: 'ACT-005',
            actionType: 'referral',
            payload: ['referredUserId' => 'USR-002'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );

        $decision = $rule->evaluate($action);

        self::assertCount(1, $decision->journalEntries);
        self::assertSame(50, $decision->journalEntries[0]->points);
        self::assertNull($decision->journalEntries[0]->sourceRef);
        self::assertCount(0, $decision->rewardGrants);
    }
}
