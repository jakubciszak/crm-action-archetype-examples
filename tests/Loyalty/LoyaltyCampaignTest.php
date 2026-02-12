<?php

declare(strict_types=1);

namespace Tests\Loyalty;

use Loyalty\Domain\IncentiveAction;
use Loyalty\Domain\IncentiveActionState;
use Loyalty\Domain\LoyaltyCampaign;
use Loyalty\Domain\PointsGranted;
use Loyalty\Domain\PointWallet;
use Loyalty\Domain\RewardGranted;
use Loyalty\Domain\WalletEntryStatus;
use Loyalty\Infrastructure\Rules\OrderPointsRule;
use Loyalty\Infrastructure\Rules\ReferralBonusRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoyaltyCampaignTest extends TestCase
{
    #[Test]
    public function full_loyalty_flow_with_wallet_pending_active_return(): void
    {
        $campaign = new LoyaltyCampaign('CAMP-001', 'Wiosna 2025');
        $wallet = new PointWallet('USR-001');
        $rules = [new OrderPointsRule(), new ReferralBonusRule()];
        $now = new \DateTimeImmutable('2025-05-12');

        // Order with 3 lines → 350 points pending
        $action1 = new IncentiveAction(
            id: 'ACT-001',
            actionType: 'order_placed',
            payload: [
                'orderId' => 'ORD-001',
                'lines' => [
                    ['lineId' => 'L1', 'productName' => 'Laptop', 'amountCents' => 300000],
                    ['lineId' => 'L2', 'productName' => 'Mysz', 'amountCents' => 15000],
                    ['lineId' => 'L3', 'productName' => 'Klawiatura', 'amountCents' => 35000],
                ],
            ],
            participantId: 'USR-001',
            occurredAt: $now,
        );
        $action1->evaluate(...$rules);
        $action1->settle();
        $campaign->recordAction($action1);

        $wallet->creditPendingFromOrder('ORD-001', $action1->decision()->journalEntries);
        self::assertSame(350, $wallet->pendingBalance());
        self::assertCount(3, $action1->decision()->journalEntries);

        // Referral → 50 points active
        $action2 = new IncentiveAction(
            id: 'ACT-002',
            actionType: 'referral',
            payload: ['referredUserId' => 'USR-002'],
            participantId: 'USR-001',
            occurredAt: $now,
        );
        $action2->evaluate(...$rules);
        $action2->settle();
        $campaign->recordAction($action2);

        $wallet->creditActive($action2->decision()->journalEntries[0], $now->modify('+3 months'));
        self::assertSame(50, $wallet->activeBalance());

        // Return mouse → debit 15 pending
        $wallet->debitForReturn('ORD-001', 'L2');
        self::assertSame(335, $wallet->pendingBalance());

        // Return period ends → 335 pending → active (6 months)
        $wallet->activateOrder('ORD-001', $now->modify('+6 months'));
        self::assertSame(0, $wallet->pendingBalance());
        self::assertSame(385, $wallet->activeBalance());

        // After 4 months: referral expires, order still active
        $wallet->expireEntries($now->modify('+4 months'));
        self::assertSame(335, $wallet->activeBalance());

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
            payload: [
                'orderId' => 'ORD-001',
                'lines' => [
                    ['lineId' => 'L1', 'productName' => 'Product', 'amountCents' => 10000],
                ],
            ],
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

    #[Test]
    public function reward_granted_for_large_order(): void
    {
        $campaign = new LoyaltyCampaign('CAMP-001', 'Test');
        $rules = [new OrderPointsRule()];

        $action = new IncentiveAction(
            id: 'ACT-001',
            actionType: 'order_placed',
            payload: [
                'orderId' => 'ORD-001',
                'lines' => [
                    ['lineId' => 'L1', 'productName' => 'Expensive', 'amountCents' => 150000],
                ],
            ],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );
        $action->evaluate(...$rules);
        $action->settle();
        $campaign->recordAction($action);

        self::assertCount(1, $action->decision()->rewardGrants);
        self::assertSame('free_shipping', $action->decision()->rewardGrants[0]->rewardId);

        $events = $action->releaseEvents();
        $grantedEvents = array_filter($events, fn($e) => $e instanceof PointsGranted);
        $rewardEvents = array_filter($events, fn($e) => $e instanceof RewardGranted);
        self::assertCount(1, $grantedEvents);
        self::assertCount(1, $rewardEvents);
    }
}
