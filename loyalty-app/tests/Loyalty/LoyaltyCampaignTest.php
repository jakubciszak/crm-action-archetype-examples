<?php

declare(strict_types=1);

namespace Tests\Loyalty;

use App\Loyalty\Domain\Event\IncentiveReversed;
use App\Loyalty\Domain\Event\IncentiveSettled;
use App\Loyalty\Domain\Model\ActivityCategory;
use App\Loyalty\Domain\Model\CategoryType;
use App\Loyalty\Domain\Model\IncentiveDecision;
use App\Loyalty\Domain\Model\LoyaltyCampaign;
use App\Loyalty\Domain\Service\DoublePointsRule;
use App\Loyalty\Domain\Service\LoyaltyProcessManager;
use App\Loyalty\Domain\Service\PointsCalculator;
use App\Loyalty\Domain\State\CampaignStatus;
use App\SharedKernel\CrmArchetype\Model\PartySignature;
use PHPUnit\Framework\TestCase;

/**
 * Chicago-style: testujemy cały przepływ procesu lojalnościowego.
 * Realne obiekty, zero mocków — sprawdzamy zachowanie end-to-end.
 *
 * Kontrast z onboardingiem: event-driven, nie sekwencyjny.
 * Struktura identyczna, flow inny.
 */
final class LoyaltyCampaignTest extends TestCase
{
    private LoyaltyProcessManager $processManager;

    protected function setUp(): void
    {
        $this->processManager = new LoyaltyProcessManager(
            new PointsCalculator(),
            new DoublePointsRule(orderThreshold: 500.0, bonusMultiplier: 2.0),
        );
    }

    private function createActiveCampaign(): LoyaltyCampaign
    {
        $campaign = new LoyaltyCampaign(
            'camp-1',
            'Sezon Wiosna 2025',
            new \DateTimeImmutable('2025-03-01'),
            new \DateTimeImmutable('2025-05-31'),
        );

        $campaign->addCategory(new ActivityCategory('cat-1', CategoryType::Purchases));
        $campaign->addCategory(new ActivityCategory('cat-2', CategoryType::Referrals));
        $campaign->addCategory(new ActivityCategory('cat-3', CategoryType::Reviews));

        $campaign->activate();

        return $campaign;
    }

    private function evaluator(): PartySignature
    {
        return new PartySignature('system', 'loyalty_engine');
    }

    // --- Kampania ---

    public function test_campaign_starts_as_draft(): void
    {
        $campaign = new LoyaltyCampaign(
            'camp-1',
            'Test',
            new \DateTimeImmutable(),
            new \DateTimeImmutable('+30 days'),
        );

        self::assertSame(CampaignStatus::Draft, $campaign->status());
        self::assertFalse($campaign->isActive());
    }

    public function test_campaign_lifecycle(): void
    {
        $campaign = new LoyaltyCampaign(
            'camp-1',
            'Test',
            new \DateTimeImmutable(),
            new \DateTimeImmutable('+30 days'),
        );

        $campaign->activate();
        self::assertTrue($campaign->isActive());

        $campaign->suspend();
        self::assertSame(CampaignStatus::Suspended, $campaign->status());

        $campaign->activate(); // resume
        self::assertTrue($campaign->isActive());

        $campaign->complete();
        self::assertSame(CampaignStatus::Completed, $campaign->status());
    }

    public function test_cannot_activate_completed_campaign(): void
    {
        $campaign = $this->createActiveCampaign();
        $campaign->complete();

        $this->expectException(\DomainException::class);
        $campaign->activate();
    }

    // --- Rejestracja aktywności ---

    public function test_record_purchase_activity(): void
    {
        $campaign = $this->createActiveCampaign();

        $action = $this->processManager->recordActivity(
            $campaign,
            CategoryType::Purchases,
            'member-1',
            'purchase',
            ['amount' => 200.0],
        );

        self::assertSame('member-1', $action->memberId());
        self::assertSame('purchase', $action->eventType());

        $category = $campaign->findCategory(CategoryType::Purchases);
        self::assertCount(1, $category->events());
    }

    public function test_cannot_record_activity_on_inactive_campaign(): void
    {
        $campaign = new LoyaltyCampaign(
            'camp-1',
            'Test',
            new \DateTimeImmutable(),
            new \DateTimeImmutable('+30 days'),
        );

        // Kampania jest Draft, nie Active
        $this->expectException(\DomainException::class);
        $this->processManager->recordActivity($campaign, CategoryType::Purchases, 'member-1', 'purchase');
    }

    // --- Pełny przepływ: zakup → ewaluacja → rozliczenie ---

    public function test_full_purchase_flow_with_points(): void
    {
        $campaign = $this->createActiveCampaign();

        // 1. Rejestracja zakupu
        $action = $this->processManager->recordActivity(
            $campaign,
            CategoryType::Purchases,
            'member-1',
            'purchase',
            ['amount' => 200.0],
        );

        // 2. Ewaluacja i zatwierdzenie (zwykły klient, 200 PLN → 200 punktów)
        $this->processManager->evaluateAndApprove(
            $action,
            CategoryType::Purchases,
            200.0,
            false,
            $this->evaluator(),
        );

        // 3. Rozliczenie
        $this->processManager->settle($action, $campaign);

        self::assertSame(200, $action->totalPointsGranted());
        self::assertCount(1, $action->journalEntries());
        self::assertTrue($action->isTerminal());
    }

    // --- Double Points Rule ---

    public function test_gold_member_gets_double_points_over_threshold(): void
    {
        $campaign = $this->createActiveCampaign();

        $action = $this->processManager->recordActivity(
            $campaign,
            CategoryType::Purchases,
            'gold-member-1',
            'purchase',
            ['amount' => 600.0],
        );

        // Gold member + 600 PLN (> 500 threshold) → 2x punkty
        $this->processManager->evaluateAndApprove(
            $action,
            CategoryType::Purchases,
            600.0,
            true,
            $this->evaluator(),
        );

        $this->processManager->settle($action, $campaign);

        // 600 PLN * 1.0 (purchases) * 2.0 (gold bonus) = 1200
        self::assertSame(1200, $action->totalPointsGranted());
    }

    public function test_gold_member_no_double_points_under_threshold(): void
    {
        $campaign = $this->createActiveCampaign();

        $action = $this->processManager->recordActivity(
            $campaign,
            CategoryType::Purchases,
            'gold-member-1',
            'purchase',
            ['amount' => 300.0],
        );

        // Gold member + 300 PLN (< 500 threshold) → zwykłe punkty
        $this->processManager->evaluateAndApprove(
            $action,
            CategoryType::Purchases,
            300.0,
            true,
            $this->evaluator(),
        );

        $this->processManager->settle($action, $campaign);

        self::assertSame(300, $action->totalPointsGranted());
    }

    // --- Mnożniki per kategoria ---

    public function test_referral_gives_double_base_points(): void
    {
        $campaign = $this->createActiveCampaign();

        $action = $this->processManager->recordActivity(
            $campaign,
            CategoryType::Referrals,
            'member-1',
            'referral',
        );

        // Referral multiplier = 2.0, base 100 → 200 punktów
        $this->processManager->evaluateAndApprove(
            $action,
            CategoryType::Referrals,
            100.0,
            false,
            $this->evaluator(),
        );

        $this->processManager->settle($action, $campaign);

        self::assertSame(200, $action->totalPointsGranted());
    }

    // --- Chargeback / reverse ---

    public function test_reverse_after_settlement_zeroes_points(): void
    {
        $campaign = $this->createActiveCampaign();

        $action = $this->processManager->recordActivity(
            $campaign,
            CategoryType::Purchases,
            'member-1',
            'purchase',
        );

        $this->processManager->evaluateAndApprove(
            $action,
            CategoryType::Purchases,
            400.0,
            false,
            $this->evaluator(),
        );

        $this->processManager->settle($action, $campaign);
        self::assertSame(400, $action->totalPointsGranted());

        // Chargeback
        $this->processManager->reverse($action, 'Zwrot towaru');

        self::assertSame(0, $action->totalPointsGranted());
        self::assertTrue($action->isTerminal());
    }

    // --- Domain events ---

    public function test_settle_emits_incentive_settled_event(): void
    {
        $campaign = $this->createActiveCampaign();

        $action = $this->processManager->recordActivity(
            $campaign,
            CategoryType::Purchases,
            'member-1',
            'purchase',
        );

        $this->processManager->evaluateAndApprove(
            $action,
            CategoryType::Purchases,
            100.0,
            false,
            $this->evaluator(),
        );

        $this->processManager->settle($action, $campaign);

        $events = $this->processManager->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(IncentiveSettled::class, $events[0]);
        self::assertSame('camp-1', $events[0]->campaignId);
        self::assertSame('member-1', $events[0]->memberId);
        self::assertSame(100, $events[0]->pointsGranted);
    }

    public function test_reverse_emits_incentive_reversed_event(): void
    {
        $campaign = $this->createActiveCampaign();

        $action = $this->processManager->recordActivity(
            $campaign,
            CategoryType::Purchases,
            'member-1',
            'purchase',
        );

        $this->processManager->evaluateAndApprove($action, CategoryType::Purchases, 100.0, false, $this->evaluator());
        $this->processManager->settle($action, $campaign);
        $this->processManager->releaseEvents(); // clear

        $this->processManager->reverse($action, 'Chargeback');

        $events = $this->processManager->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(IncentiveReversed::class, $events[0]);
        self::assertSame('Chargeback', $events[0]->reason);
    }

    // --- Wiele niezależnych zdarzeń (event-driven) ---

    public function test_multiple_independent_activities_in_same_campaign(): void
    {
        $campaign = $this->createActiveCampaign();

        // Trzy niezależne zdarzenia
        $purchase = $this->processManager->recordActivity(
            $campaign, CategoryType::Purchases, 'member-1', 'purchase',
        );
        $referral = $this->processManager->recordActivity(
            $campaign, CategoryType::Referrals, 'member-1', 'referral',
        );
        $review = $this->processManager->recordActivity(
            $campaign, CategoryType::Reviews, 'member-1', 'review',
        );

        // Każde przetwarzane niezależnie
        $this->processManager->evaluateAndApprove($purchase, CategoryType::Purchases, 100.0, false, $this->evaluator());
        $this->processManager->evaluateAndApprove($referral, CategoryType::Referrals, 50.0, false, $this->evaluator());
        $this->processManager->evaluateAndApprove($review, CategoryType::Reviews, 100.0, false, $this->evaluator());

        $this->processManager->settle($purchase, $campaign);
        $this->processManager->settle($referral, $campaign);
        $this->processManager->settle($review, $campaign);

        // Purchases: 100 * 1.0 = 100, Referrals: 50 * 2.0 = 100, Reviews: 100 * 0.5 = 50
        self::assertSame(100, $purchase->totalPointsGranted());
        self::assertSame(100, $referral->totalPointsGranted());
        self::assertSame(50, $review->totalPointsGranted());
    }

    // --- Category tracking ---

    public function test_category_tracks_settled_incentives(): void
    {
        $campaign = $this->createActiveCampaign();

        $action = $this->processManager->recordActivity(
            $campaign, CategoryType::Purchases, 'member-1', 'purchase',
        );

        $this->processManager->evaluateAndApprove($action, CategoryType::Purchases, 100.0, false, $this->evaluator());
        $this->processManager->settle($action, $campaign);

        $category = $campaign->findCategory(CategoryType::Purchases);
        self::assertSame(1, $category->totalSettledIncentives());
    }
}
