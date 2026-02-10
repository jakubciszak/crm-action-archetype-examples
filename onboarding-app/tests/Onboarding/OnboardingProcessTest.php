<?php

declare(strict_types=1);

namespace Tests\Onboarding;

use App\Onboarding\Domain\Event\StepCompleted;
use App\Onboarding\Domain\Event\SupplementRequired;
use App\Onboarding\Domain\Model\CustomerType;
use App\Onboarding\Domain\Model\OnboardingCase;
use App\Onboarding\Domain\Model\OnboardingStep;
use App\Onboarding\Domain\Model\StepResult;
use App\Onboarding\Domain\Service\OnboardingProcessManager;
use App\Onboarding\Domain\Service\OnboardingScenarioBuilder;
use App\SharedKernel\CrmArchetype\Model\PartySignature;
use PHPUnit\Framework\TestCase;

/**
 * Chicago-style: testujemy cały przepływ procesu onboardingowego.
 * Realne obiekty, zero mocków — sprawdzamy zachowanie end-to-end.
 */
final class OnboardingProcessTest extends TestCase
{
    private OnboardingProcessManager $processManager;
    private OnboardingScenarioBuilder $scenarioBuilder;

    protected function setUp(): void
    {
        $this->processManager = new OnboardingProcessManager();
        $this->scenarioBuilder = new OnboardingScenarioBuilder();
    }

    private function approver(): PartySignature
    {
        return new PartySignature('approver-1', 'compliance');
    }

    private function performer(): PartySignature
    {
        return new PartySignature('officer-1', 'kyc_officer');
    }

    // --- Budowanie scenariuszy ---

    public function test_enterprise_scenario_has_four_stages(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::Enterprise);
        $this->scenarioBuilder->buildForCase($case);

        self::assertCount(4, $case->stages());
        self::assertSame('kyc', $case->stages()[0]->type()->value);
        self::assertSame('contract', $case->stages()[1]->type()->value);
        self::assertSame('setup', $case->stages()[2]->type()->value);
        self::assertSame('training', $case->stages()[3]->type()->value);
    }

    public function test_sme_scenario_has_three_stages(): void
    {
        $case = new OnboardingCase('case-2', 'customer-2', CustomerType::SME);
        $this->scenarioBuilder->buildForCase($case);

        self::assertCount(3, $case->stages());
        self::assertSame('kyc', $case->stages()[0]->type()->value);
        self::assertSame('contract', $case->stages()[1]->type()->value);
        self::assertSame('setup', $case->stages()[2]->type()->value);
    }

    public function test_enterprise_kyc_has_three_steps(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::Enterprise);
        $this->scenarioBuilder->buildForCase($case);

        $kycStage = $case->stages()[0];
        $steps = $this->extractSteps($kycStage);

        self::assertCount(3, $steps);
        self::assertSame('Weryfikacja dokumentów', $steps[0]->name());
        self::assertSame('Weryfikacja beneficjentów', $steps[1]->name());
        self::assertSame('AML Screening', $steps[2]->name());
    }

    public function test_sme_kyc_has_one_automatic_step(): void
    {
        $case = new OnboardingCase('case-2', 'customer-2', CustomerType::SME);
        $this->scenarioBuilder->buildForCase($case);

        $kycStage = $case->stages()[0];
        $steps = $this->extractSteps($kycStage);

        self::assertCount(1, $steps);
        self::assertSame('Automatyczna weryfikacja KYC', $steps[0]->name());
    }

    // --- Przepływ procesu ---

    public function test_starting_case_advances_to_first_stage(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::Enterprise);
        $this->scenarioBuilder->buildForCase($case);

        $this->processManager->startCase($case);

        self::assertNotNull($case->currentStage());
        self::assertSame('kyc', $case->currentStage()->type()->value);
    }

    public function test_completing_all_steps_in_stage_advances_to_next_stage(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::SME);
        $this->scenarioBuilder->buildForCase($case);
        $this->processManager->startCase($case);

        // Zakończ jedyny krok KYC
        $kycStage = $case->currentStage();
        $kycStep = $this->extractSteps($kycStage)[0];
        $this->completeStepWithAccept($kycStep);

        $this->processManager->completeStep(
            $case,
            $kycStage,
            $kycStep,
            StepResult::accepted(),
            $this->approver(),
        );

        // Powinno przejść do Contract stage
        self::assertTrue($kycStage->isCompleted());
        self::assertSame('contract', $case->currentStage()->type()->value);
    }

    public function test_completing_all_stages_closes_case(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::SME);
        $this->scenarioBuilder->buildForCase($case);
        $this->processManager->startCase($case);

        // Zakończ wszystkie stage'e SME (3 stage'y, po 1 stepie)
        foreach ($case->stages() as $stage) {
            $steps = $this->extractSteps($stage);
            foreach ($steps as $step) {
                $this->completeStepWithAccept($step);
                $this->processManager->completeStep(
                    $case,
                    $stage,
                    $step,
                    StepResult::accepted(),
                    $this->approver(),
                );
            }
        }

        self::assertFalse($case->isOpen());
        self::assertSame('closed', $case->status());
        self::assertTrue($case->isFullyCompleted());
    }

    // --- Pętla zwrotna: NeedsSupplement ---

    public function test_needs_supplement_creates_feedback_loop_with_new_step(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::SME);
        $this->scenarioBuilder->buildForCase($case);
        $this->processManager->startCase($case);

        $kycStage = $case->currentStage();
        $kycStep = $this->extractSteps($kycStage)[0];
        $this->completeStepWithAccept($kycStep);

        // Wynik: wymaga uzupełnienia → pętla zwrotna
        $outcome = StepResult::needsSupplement('Brak KRS');
        $this->processManager->completeStep($case, $kycStage, $kycStep, $outcome, $this->approver());

        // Powinien powstać nowy Communication + Step w tym samym stage
        self::assertCount(2, $kycStage->communications());
        $supplementCommunication = $kycStage->communications()[1];
        self::assertCount(1, $supplementCommunication->actions());

        $supplementStep = $supplementCommunication->actions()[0];
        self::assertInstanceOf(OnboardingStep::class, $supplementStep);
        self::assertStringContainsString('Uzupełnienie:', $supplementStep->name());

        // Stage nie powinien być completed — bo nowy step czeka
        self::assertFalse($kycStage->isCompleted());
    }

    public function test_supplement_emits_domain_event(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::SME);
        $this->scenarioBuilder->buildForCase($case);
        $this->processManager->startCase($case);

        $kycStage = $case->currentStage();
        $kycStep = $this->extractSteps($kycStage)[0];
        $this->completeStepWithAccept($kycStep);

        $outcome = StepResult::needsSupplement('Brak KRS');
        $this->processManager->completeStep($case, $kycStage, $kycStep, $outcome, $this->approver());

        $events = $this->processManager->releaseEvents();
        self::assertCount(2, $events); // StepCompleted + SupplementRequired

        self::assertInstanceOf(StepCompleted::class, $events[0]);
        self::assertInstanceOf(SupplementRequired::class, $events[1]);
        self::assertSame('Brak KRS', $events[1]->reason);
    }

    // --- Rejected ---

    public function test_rejected_step_closes_case_as_rejected(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::Enterprise);
        $this->scenarioBuilder->buildForCase($case);
        $this->processManager->startCase($case);

        $kycStage = $case->currentStage();
        $kycStep = $this->extractSteps($kycStage)[0];
        $this->completeStepWithAccept($kycStep);

        $outcome = StepResult::rejected('Firma na liście sankcyjnej');
        $this->processManager->completeStep($case, $kycStage, $kycStep, $outcome, $this->approver());

        self::assertSame('rejected', $case->status());
    }

    // --- Events ---

    public function test_step_completed_event_is_emitted(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::SME);
        $this->scenarioBuilder->buildForCase($case);
        $this->processManager->startCase($case);

        $stage = $case->currentStage();
        $step = $this->extractSteps($stage)[0];
        $this->completeStepWithAccept($step);

        $this->processManager->completeStep(
            $case,
            $stage,
            $step,
            StepResult::accepted(),
            $this->approver(),
        );

        $events = $this->processManager->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StepCompleted::class, $events[0]);
        self::assertSame('accepted', $events[0]->outcomeCode);
    }

    // --- Case invariants ---

    public function test_cannot_start_case_without_stages(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::Enterprise);

        $this->expectException(\DomainException::class);
        $this->processManager->startCase($case);
    }

    public function test_cannot_advance_to_next_stage_if_current_not_completed(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::SME);
        $this->scenarioBuilder->buildForCase($case);
        $this->processManager->startCase($case);

        $this->expectException(\DomainException::class);
        $case->advanceToStage($case->stages()[1]);
    }

    // --- Enterprise full flow (multiple steps per stage) ---

    public function test_enterprise_kyc_requires_all_steps_completed_to_advance(): void
    {
        $case = new OnboardingCase('case-1', 'customer-1', CustomerType::Enterprise);
        $this->scenarioBuilder->buildForCase($case);
        $this->processManager->startCase($case);

        $kycStage = $case->currentStage();
        $steps = $this->extractSteps($kycStage);

        // Zakończ 2 z 3 kroków
        $this->completeStepWithAccept($steps[0]);
        $this->processManager->completeStep($case, $kycStage, $steps[0], StepResult::accepted(), $this->approver());

        $this->completeStepWithAccept($steps[1]);
        $this->processManager->completeStep($case, $kycStage, $steps[1], StepResult::accepted(), $this->approver());

        // Stage nadal KYC (trzeci krok nie zakończony)
        self::assertSame('kyc', $case->currentStage()->type()->value);
        self::assertFalse($kycStage->isCompleted());

        // Zakończ trzeci krok
        $this->completeStepWithAccept($steps[2]);
        $this->processManager->completeStep($case, $kycStage, $steps[2], StepResult::accepted(), $this->approver());

        // Teraz powinno przejść do Contract
        self::assertTrue($kycStage->isCompleted());
        self::assertSame('contract', $case->currentStage()->type()->value);
    }

    // --- Helpers ---

    /**
     * Przeprowadza step przez cykl życia do momentu przed approve.
     * (submit → start → requestApproval)
     */
    private function completeStepWithAccept(OnboardingStep $step): void
    {
        $step->submit();
        $step->start($this->performer());
        $step->requestApproval();
    }

    /**
     * @return OnboardingStep[]
     */
    private function extractSteps($stage): array
    {
        $steps = [];
        foreach ($stage->communications() as $comm) {
            foreach ($comm->actions() as $action) {
                if ($action instanceof OnboardingStep) {
                    $steps[] = $action;
                }
            }
        }

        return $steps;
    }
}
