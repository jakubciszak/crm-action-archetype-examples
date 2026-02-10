<?php

declare(strict_types=1);

namespace Tests\Onboarding;

use App\Onboarding\Domain\Model\OnboardingStep;
use App\Onboarding\Domain\Model\StepResult;
use App\Onboarding\Domain\State\OnboardingStepStatus;
use App\SharedKernel\CrmArchetype\Model\PartySignature;
use App\SharedKernel\CrmArchetype\State\ActionStatus;
use PHPUnit\Framework\TestCase;

/**
 * Chicago-style: testujemy zachowanie OnboardingStep przez publiczne API.
 * Realne obiekty, brak mocków — sprawdzamy przejścia stanów i outcomes.
 */
final class OnboardingStepLifecycleTest extends TestCase
{
    private function createStep(): OnboardingStep
    {
        return OnboardingStep::fromBlueprint(
            'step-1',
            'Weryfikacja KYC',
            StepResult::standardPossibleOutcomes(),
        );
    }

    private function performer(): PartySignature
    {
        return new PartySignature('user-1', 'kyc_officer');
    }

    private function approver(): PartySignature
    {
        return new PartySignature('user-2', 'compliance_manager');
    }

    // --- Tworzenie z blueprintu ---

    public function test_step_created_from_blueprint_has_possible_outcomes(): void
    {
        $step = $this->createStep();

        self::assertCount(3, $step->possibleOutcomes());
        self::assertSame('Weryfikacja KYC', $step->name());
        self::assertSame(OnboardingStepStatus::Draft, $step->stepStatus());
        self::assertSame(ActionStatus::Pending, $step->status());
    }

    public function test_step_has_no_actual_outcomes_on_creation(): void
    {
        $step = $this->createStep();

        self::assertEmpty($step->actualOutcomes());
        self::assertFalse($step->isAccepted());
        self::assertFalse($step->requiresSupplement());
        self::assertFalse($step->isRejected());
    }

    // --- Pełny cykl życia: happy path ---

    public function test_full_lifecycle_draft_to_completed(): void
    {
        $step = $this->createStep();

        // Draft → Pending
        $step->submit();
        self::assertSame(OnboardingStepStatus::Pending, $step->stepStatus());

        // Pending → InProgress
        $step->start($this->performer());
        self::assertSame(OnboardingStepStatus::InProgress, $step->stepStatus());
        self::assertSame(ActionStatus::Open, $step->status());

        // InProgress → AwaitingApproval
        $step->requestApproval();
        self::assertSame(OnboardingStepStatus::AwaitingApproval, $step->stepStatus());

        // AwaitingApproval → Completed (z outcome Accepted)
        $step->approve(StepResult::accepted(), $this->approver());
        self::assertSame(OnboardingStepStatus::Completed, $step->stepStatus());
        self::assertSame(ActionStatus::Closed, $step->status());
        self::assertTrue($step->isAccepted());
        self::assertTrue($step->isTerminal());
    }

    // --- Przejścia stanów: guard conditions ---

    public function test_cannot_start_step_directly_from_draft(): void
    {
        $step = $this->createStep();

        $this->expectException(\DomainException::class);
        $step->start($this->performer());
    }

    public function test_cannot_approve_step_in_progress(): void
    {
        $step = $this->createStep();
        $step->submit();
        $step->start($this->performer());

        $this->expectException(\DomainException::class);
        $step->approve(StepResult::accepted(), $this->approver());
    }

    public function test_cannot_transition_from_completed(): void
    {
        $step = $this->createStep();
        $step->submit();
        $step->start($this->performer());
        $step->requestApproval();
        $step->approve(StepResult::accepted(), $this->approver());

        $this->expectException(\DomainException::class);
        $step->submit();
    }

    // --- Outcome: NeedsSupplement — pętla zwrotna ---

    public function test_needs_supplement_outcome_triggers_feedback_loop(): void
    {
        $step = $this->createStep();
        $step->submit();
        $step->start($this->performer());
        $step->requestApproval();

        $outcome = StepResult::needsSupplement('Brak KRS', $this->approver());
        $step->approve($outcome, $this->approver());

        self::assertTrue($step->requiresSupplement());
        self::assertFalse($step->isAccepted());
        self::assertCount(1, $step->actualOutcomes());
        self::assertSame('needs_supplement', $step->actualOutcomes()[0]->code());
        self::assertSame('Brak KRS', $step->actualOutcomes()[0]->metadata()['reason']);
    }

    // --- Outcome: Rejected ---

    public function test_rejected_outcome(): void
    {
        $step = $this->createStep();
        $step->submit();
        $step->start($this->performer());
        $step->requestApproval();

        $outcome = StepResult::rejected('Firma na liście sankcyjnej', $this->approver());
        $step->approve($outcome, $this->approver());

        self::assertTrue($step->isRejected());
        self::assertFalse($step->isAccepted());
    }

    // --- OnHold i resume ---

    public function test_step_can_be_put_on_hold_and_resumed(): void
    {
        $step = $this->createStep();
        $step->submit();
        $step->start($this->performer());

        $step->hold();
        self::assertSame(OnboardingStepStatus::OnHold, $step->stepStatus());

        $step->resume();
        self::assertSame(OnboardingStepStatus::InProgress, $step->stepStatus());
    }

    // --- Failed i eskalacja ---

    public function test_failed_step_can_be_retried(): void
    {
        $step = $this->createStep();
        $step->submit();
        $step->start($this->performer());

        $step->fail(StepResult::rejected('Błąd systemu zewnętrznego'));
        self::assertSame(OnboardingStepStatus::Failed, $step->stepStatus());

        // Failed → InProgress: retry
        $step->resume();
        self::assertSame(OnboardingStepStatus::InProgress, $step->stepStatus());
    }

    public function test_failed_step_can_be_escalated(): void
    {
        $step = $this->createStep();
        $step->submit();
        $step->start($this->performer());
        $step->fail(StepResult::rejected('Timeout'));

        $step->escalate();
        self::assertSame(OnboardingStepStatus::Escalated, $step->stepStatus());
        self::assertTrue($step->isTerminal());
        self::assertSame(ActionStatus::Closed, $step->status());
    }

    // --- PartySignature / audit trail ---

    public function test_performer_and_approver_are_recorded(): void
    {
        $step = $this->createStep();
        $step->submit();

        $performer = $this->performer();
        $step->start($performer);

        self::assertSame('user-1', $step->performedBy()->partyId());
        self::assertSame('kyc_officer', $step->performedBy()->role());

        $step->requestApproval();
        $approver = $this->approver();
        $step->approve(StepResult::accepted(), $approver);

        self::assertSame('user-2', $step->approvedBy()->partyId());
    }

    // --- Context ---

    public function test_step_can_carry_context(): void
    {
        $step = $this->createStep();
        $step->withContext(['company_name' => 'Acme Corp', 'nip' => '1234567890']);

        self::assertSame('Acme Corp', $step->context()['company_name']);
    }
}
