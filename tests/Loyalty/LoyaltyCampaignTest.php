<?php

declare(strict_types=1);

namespace Tests\Loyalty;

use Loyalty\Domain\IncentiveAction;
use Loyalty\Domain\IncentiveActionState;
use Loyalty\Domain\LoyaltyCampaign;
use Loyalty\Domain\PointsDebited;
use Loyalty\Domain\PointsGranted;
use Loyalty\Domain\RewardGranted;
use Loyalty\Infrastructure\Rules\OrderPointsRule;
use Loyalty\Infrastructure\Rules\ReferralBonusRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoyaltyCampaignTest extends TestCase
{
    #[Test]
    public function full_loyalty_flow_with_purchase_referral_and_chargeback(): void
    {
        $campaign = new LoyaltyCampaign('CAMP-001', 'Wiosna 2025');
        $rules = [new OrderPointsRule(), new ReferralBonusRule()];

        // Purchase 250 zł → 25 points
        $action1 = new IncentiveAction(
            id: 'ACT-001',
            actionType: 'order_placed',
            payload: ['totalAmountCents' => 25000, 'orderId' => 'ORD-001'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );
        $action1->evaluate(...$rules);
        $action1->settle();
        $campaign->recordAction($action1);

        $events1 = $action1->releaseEvents();
        self::assertSame(IncentiveActionState::Settled, $action1->state());
        self::assertCount(1, $events1);
        self::assertInstanceOf(PointsGranted::class, $events1[0]);
        self::assertSame(25, $action1->decision()->journalEntries[0]->points);

        // Referral → 50 points bonus
        $action2 = new IncentiveAction(
            id: 'ACT-002',
            actionType: 'referral',
            payload: ['referredUserId' => 'USR-002'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );
        $action2->evaluate(...$rules);
        $action2->settle();
        $campaign->recordAction($action2);

        $events2 = $action2->releaseEvents();
        self::assertSame(50, $action2->decision()->journalEntries[0]->points);

        // Purchase 1500 zł → 150 points + reward
        $action3 = new IncentiveAction(
            id: 'ACT-003',
            actionType: 'order_placed',
            payload: ['totalAmountCents' => 150000, 'orderId' => 'ORD-002'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );
        $action3->evaluate(...$rules);
        $action3->settle();
        $campaign->recordAction($action3);

        $events3 = $action3->releaseEvents();
        self::assertSame(150, $action3->decision()->journalEntries[0]->points);
        self::assertCount(1, $action3->decision()->rewardGrants);
        self::assertCount(2, $events3);
        self::assertInstanceOf(PointsGranted::class, $events3[0]);
        self::assertInstanceOf(RewardGranted::class, $events3[1]);

        // Chargeback
        $action3->reverse('Chargeback');
        $events4 = $action3->releaseEvents();
        self::assertSame(IncentiveActionState::Reversed, $action3->state());
        self::assertCount(1, $events4);
        self::assertInstanceOf(PointsDebited::class, $events4[0]);

        // Verify streams
        self::assertCount(2, $campaign->streams());
        self::assertNotNull($campaign->findStream('order_placed'));
        self::assertNotNull($campaign->findStream('referral'));
    }

    #[Test]
    public function records_action_to_correct_stream(): void
    {
        $campaign = new LoyaltyCampaign('CAMP-001', 'Test');

        $action = new IncentiveAction(
            id: 'ACT-001',
            actionType: 'order_placed',
            payload: ['totalAmountCents' => 10000, 'orderId' => 'ORD-001'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );
        $campaign->recordAction($action);

        $stream = $campaign->findStream('order_placed');
        self::assertNotNull($stream);
        self::assertCount(1, $stream->actions());
    }

    #[Test]
    public function campaign_accessors(): void
    {
        $campaign = new LoyaltyCampaign('CAMP-001', 'Wiosna 2025');

        self::assertSame('CAMP-001', $campaign->id());
        self::assertSame('Wiosna 2025', $campaign->name());
        self::assertCount(0, $campaign->streams());
    }
}
