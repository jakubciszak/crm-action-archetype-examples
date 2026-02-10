<?php

declare(strict_types=1);

namespace Tests\Loyalty;

use App\Loyalty\Domain\Model\IncentiveAction;
use App\Loyalty\Domain\Model\IncentiveDecision;
use App\Loyalty\Domain\State\IncentiveActionStatus;
use App\SharedKernel\CrmArchetype\Model\PartySignature;
use PHPUnit\Framework\TestCase;

/**
 * Chicago-style: testujemy zachowanie IncentiveAction przez publiczne API.
 * Realne obiekty, brak mocków.
 *
 * IncentiveAction NIE dziedziczy po Action — używa kompozycji.
 * Zupełnie inna maszyna stanów niż OnboardingStep.
 */
final class IncentiveActionLifecycleTest extends TestCase
{
    private function createAction(): IncentiveAction
    {
        return new IncentiveAction('act-1', 'member-1', 'purchase');
    }

    private function evaluator(): PartySignature
    {
        return new PartySignature('system', 'loyalty_engine');
    }

    // --- Tworzenie ---

    public function test_action_starts_in_received_state(): void
    {
        $action = $this->createAction();

        self::assertSame(IncentiveActionStatus::Received, $action->status());
        self::assertSame('member-1', $action->memberId());
        self::assertSame('purchase', $action->eventType());
        self::assertCount(3, $action->possibleOutcomes());
        self::assertEmpty($action->actualOutcomes());
    }

    // --- Pełny happy path: Received → Evaluating → AwaitingSettlement → Settled ---

    public function test_full_lifecycle_to_settled(): void
    {
        $action = $this->createAction();

        // Received → Evaluating
        $action->evaluate($this->evaluator());
        self::assertSame(IncentiveActionStatus::Evaluating, $action->status());

        // Evaluating → AwaitingSettlement
        $outcome = IncentiveDecision::pointsGranted(100, 'member-1');
        $action->approveForSettlement($outcome);
        self::assertSame(IncentiveActionStatus::AwaitingSettlement, $action->status());

        // AwaitingSettlement → Settled
        $action->settle($outcome);
        self::assertSame(IncentiveActionStatus::Settled, $action->status());
        self::assertTrue($action->isTerminal());
    }

    // --- Settled emituje efekty biznesowe (journal entries) ---

    public function test_settle_with_points_creates_journal_entry(): void
    {
        $action = $this->createAction();
        $action->evaluate($this->evaluator());

        $outcome = IncentiveDecision::pointsGranted(250, 'member-1', 'Punkty za zakup');
        $action->approveForSettlement($outcome);
        $action->settle($outcome);

        self::assertCount(1, $action->journalEntries());
        self::assertSame(250, $action->journalEntries()[0]->points());
        self::assertSame('member-1', $action->journalEntries()[0]->memberId());
        self::assertTrue($action->journalEntries()[0]->isCredit());
        self::assertSame(250, $action->totalPointsGranted());
    }

    public function test_settle_with_reward_creates_reward_grant(): void
    {
        $action = $this->createAction();
        $action->evaluate($this->evaluator());

        $outcome = IncentiveDecision::rewardGrant('COUPON-10', 'member-1', 'Kupon rabatowy 10%');
        $action->approveForSettlement($outcome);
        $action->settle($outcome);

        self::assertCount(1, $action->rewardGrants());
        self::assertSame('COUPON-10', $action->rewardGrants()[0]->rewardCode());
        self::assertSame('member-1', $action->rewardGrants()[0]->memberId());
    }

    // --- Rejected ---

    public function test_evaluating_can_be_rejected(): void
    {
        $action = $this->createAction();
        $action->evaluate($this->evaluator());

        $outcome = IncentiveDecision::rejected('Podejrzana aktywność');
        $action->reject($outcome);

        self::assertSame(IncentiveActionStatus::Rejected, $action->status());
        self::assertTrue($action->isTerminal());
        self::assertTrue($action->hasOutcome(IncentiveDecision::REJECTED));
    }

    // --- Reverse (chargeback) — stan spoza archetypu ---

    public function test_settled_action_can_be_reversed(): void
    {
        $action = $this->createAction();
        $action->evaluate($this->evaluator());

        $outcome = IncentiveDecision::pointsGranted(500, 'member-1');
        $action->approveForSettlement($outcome);
        $action->settle($outcome);

        self::assertSame(500, $action->totalPointsGranted());

        // Chargeback → cofnięcie punktów
        $action->reverse('Chargeback od klienta');

        self::assertSame(IncentiveActionStatus::Reversed, $action->status());
        self::assertTrue($action->isTerminal());
        self::assertSame(0, $action->totalPointsGranted()); // 500 + (-500) = 0
        self::assertTrue($action->hasOutcome('reversed'));
    }

    public function test_reverse_creates_debit_journal_entries(): void
    {
        $action = $this->createAction();
        $action->evaluate($this->evaluator());

        $outcome = IncentiveDecision::pointsGranted(300, 'member-1');
        $action->approveForSettlement($outcome);
        $action->settle($outcome);
        $action->reverse('Zwrot towaru');

        $entries = $action->journalEntries();
        // Original credit + reversal debit
        self::assertCount(2, $entries);
        self::assertTrue($entries[0]->isCredit());
        self::assertTrue($entries[1]->isDebit());
        self::assertSame(-300, $entries[1]->points());
    }

    // --- Guard conditions: niedozwolone przejścia ---

    public function test_cannot_settle_directly_from_received(): void
    {
        $action = $this->createAction();

        $this->expectException(\DomainException::class);
        $action->settle(IncentiveDecision::pointsGranted(100, 'member-1'));
    }

    public function test_cannot_evaluate_twice(): void
    {
        $action = $this->createAction();
        $action->evaluate($this->evaluator());

        $this->expectException(\DomainException::class);
        $action->evaluate($this->evaluator());
    }

    public function test_cannot_reverse_non_settled_action(): void
    {
        $action = $this->createAction();
        $action->evaluate($this->evaluator());

        $this->expectException(\DomainException::class);
        $action->reverse('Chargeback');
    }

    public function test_cannot_reject_after_approval(): void
    {
        $action = $this->createAction();
        $action->evaluate($this->evaluator());

        $outcome = IncentiveDecision::pointsGranted(100, 'member-1');
        $action->approveForSettlement($outcome);

        $this->expectException(\DomainException::class);
        $action->reject(IncentiveDecision::rejected('Too late'));
    }

    public function test_rejected_is_terminal(): void
    {
        $action = $this->createAction();
        $action->evaluate($this->evaluator());
        $action->reject(IncentiveDecision::rejected('Fraud'));

        $this->expectException(\DomainException::class);
        $action->evaluate($this->evaluator());
    }
}
