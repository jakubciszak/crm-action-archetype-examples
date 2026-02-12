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
    public function order_points_rule_calculates_1_point_per_10_zl(): void
    {
        $rule = new OrderPointsRule();
        $action = new IncentiveAction(
            id: 'ACT-001',
            actionType: 'order_placed',
            payload: ['totalAmountCents' => 25000, 'orderId' => 'ORD-001'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );

        $decision = $rule->evaluate($action);

        self::assertCount(1, $decision->journalEntries);
        self::assertSame(25, $decision->journalEntries[0]->points);
        self::assertCount(0, $decision->rewardGrants);
    }

    #[Test]
    public function order_points_rule_grants_reward_at_100_points(): void
    {
        $rule = new OrderPointsRule();
        $action = new IncentiveAction(
            id: 'ACT-002',
            actionType: 'order_placed',
            payload: ['totalAmountCents' => 150000, 'orderId' => 'ORD-002'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );

        $decision = $rule->evaluate($action);

        self::assertSame(150, $decision->journalEntries[0]->points);
        self::assertCount(1, $decision->rewardGrants);
        self::assertSame('free_shipping', $decision->rewardGrants[0]->rewardId);
    }

    #[Test]
    public function order_points_rule_no_reward_below_100_points(): void
    {
        $rule = new OrderPointsRule();
        $action = new IncentiveAction(
            id: 'ACT-003',
            actionType: 'order_placed',
            payload: ['totalAmountCents' => 99000, 'orderId' => 'ORD-003'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );

        $decision = $rule->evaluate($action);

        self::assertSame(99, $decision->journalEntries[0]->points);
        self::assertCount(0, $decision->rewardGrants);
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
            id: 'ACT-004',
            actionType: 'referral',
            payload: ['referredUserId' => 'USR-002'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );

        $decision = $rule->evaluate($action);

        self::assertCount(1, $decision->journalEntries);
        self::assertSame(50, $decision->journalEntries[0]->points);
        self::assertCount(0, $decision->rewardGrants);
    }
}
