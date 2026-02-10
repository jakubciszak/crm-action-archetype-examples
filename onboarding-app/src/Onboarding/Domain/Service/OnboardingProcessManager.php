<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Service;

use App\Onboarding\Domain\Event\DomainEvent;
use App\Onboarding\Domain\Event\StepCompleted;
use App\Onboarding\Domain\Event\SupplementRequired;
use App\Onboarding\Domain\Model\OnboardingCase;
use App\Onboarding\Domain\Model\OnboardingStage;
use App\Onboarding\Domain\Model\OnboardingStep;
use App\Onboarding\Domain\Model\OnboardingTrigger;
use App\Onboarding\Domain\Model\StepResult;
use App\SharedKernel\CrmArchetype\Model\Outcome;
use App\SharedKernel\CrmArchetype\Model\PartySignature;

/**
 * Zarządza przepływem procesu onboardingowego.
 *
 * Realizuje logikę archetypu:
 * - Pozytywny Outcome → następna faza
 * - Negatywny Outcome (NeedsSupplement) → pętla zwrotna, nowa Communication
 * - Rejected → zamknij Case
 */
final class OnboardingProcessManager
{
    /** @var DomainEvent[] */
    private array $recordedEvents = [];

    private int $idCounter = 1000;

    public function startCase(OnboardingCase $case): void
    {
        $stages = $case->stages();
        if (count($stages) === 0) {
            throw new \DomainException('Case nie ma zdefiniowanych stage\'ów.');
        }

        usort($stages, fn (OnboardingStage $a, OnboardingStage $b) => $a->order() <=> $b->order());
        $case->advanceToStage($stages[0]);
    }

    /**
     * Przetwarza zakończenie stepu i decyduje o dalszym przepływie.
     */
    public function completeStep(
        OnboardingCase $case,
        OnboardingStage $stage,
        OnboardingStep $step,
        Outcome $outcome,
        PartySignature $approver,
    ): void {
        $step->approve($outcome, $approver);

        $this->recordedEvents[] = new StepCompleted(
            $case->id(),
            $stage->id(),
            $step->id(),
            $outcome->code(),
        );

        if ($step->requiresSupplement()) {
            $this->handleSupplement($case, $stage, $step, $outcome);

            return;
        }

        if ($step->isRejected()) {
            $case->reject();

            return;
        }

        // Accepted — sprawdź czy stage jest zakończony
        if ($stage->allStepsTerminal()) {
            $case->completeCurrentStage();
            $this->advanceToNextStage($case);
        }
    }

    /**
     * Pętla zwrotna: negatywny Outcome generuje nową Communication → Action.
     */
    private function handleSupplement(
        OnboardingCase $case,
        OnboardingStage $stage,
        OnboardingStep $failedStep,
        Outcome $outcome,
    ): void {
        $reason = $outcome->metadata()['reason'] ?? 'Brak szczegółów';

        $supplementStep = OnboardingStep::fromBlueprint(
            $this->nextId(),
            'Uzupełnienie: ' . $failedStep->name(),
            StepResult::standardPossibleOutcomes(),
        );

        $stage->requestSupplement($this->nextId(), $supplementStep);

        $this->recordedEvents[] = new SupplementRequired(
            $case->id(),
            $stage->id(),
            $failedStep->id(),
            $reason,
        );
    }

    private function advanceToNextStage(OnboardingCase $case): void
    {
        $stages = $case->stages();
        usort($stages, fn (OnboardingStage $a, OnboardingStage $b) => $a->order() <=> $b->order());

        $foundCurrent = false;
        foreach ($stages as $stage) {
            if ($foundCurrent && !$stage->isCompleted()) {
                $case->advanceToStage($stage);

                return;
            }
            if ($stage === $case->currentStage()) {
                $foundCurrent = true;
            }
        }

        // Wszystkie stage'e zakończone
        if ($case->isFullyCompleted()) {
            $case->close();
        }
    }

    /** @return DomainEvent[] */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function nextId(): string
    {
        return 'gen-' . ++$this->idCounter;
    }
}
