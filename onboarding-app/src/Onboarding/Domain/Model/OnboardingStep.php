<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Model;

use App\Onboarding\Domain\State\OnboardingStepStatus;
use App\SharedKernel\CrmArchetype\Model\Action;
use App\SharedKernel\CrmArchetype\Model\Outcome;
use App\SharedKernel\CrmArchetype\Model\PartySignature;
use App\SharedKernel\CrmArchetype\State\ActionStatus;

/**
 * OnboardingStep DZIEDZICZY po Action — bo maszyna stanów pasuje 1:1.
 * Domena dodaje swoją logikę, rdzeń daje mechanikę.
 *
 * fromBlueprint() — step tworzony z deklaratywnego scenariusza.
 * possibleOutcomes ładowane z blueprintu, znane zanim step się zacznie.
 */
class OnboardingStep extends Action
{
    private OnboardingStepStatus $stepStatus;
    private string $name;

    /** @var array<string, mixed> */
    private array $context = [];

    public function __construct(string $id, string $name)
    {
        parent::__construct($id);
        $this->name = $name;
        $this->stepStatus = OnboardingStepStatus::Draft;
    }

    /**
     * Step tworzony z deklaratywnego scenariusza (blueprintu).
     * possibleOutcomes znane zanim step się zacznie.
     *
     * @param Outcome[] $possibleOutcomes
     */
    public static function fromBlueprint(
        string $id,
        string $name,
        array $possibleOutcomes,
    ): self {
        $step = new self($id, $name);
        $step->definePossibleOutcomes($possibleOutcomes);

        return $step;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function stepStatus(): OnboardingStepStatus
    {
        return $this->stepStatus;
    }

    /**
     * Draft → Pending: submit do weryfikacji. Waliduje, że step ma possibleOutcomes.
     */
    public function submit(): void
    {
        $this->transitionTo(OnboardingStepStatus::Pending);
        $this->status = ActionStatus::Pending;
    }

    /**
     * Pending → InProgress: rozpocznij pracę, przypisz wykonawcę.
     */
    public function start(PartySignature $performer): void
    {
        $this->transitionTo(OnboardingStepStatus::InProgress);
        $this->assignPerformer($performer);
        $this->status = ActionStatus::Open;
    }

    /**
     * InProgress → AwaitingApproval: praca wykonana, czeka na zatwierdzenie.
     */
    public function requestApproval(): void
    {
        $this->transitionTo(OnboardingStepStatus::AwaitingApproval);
    }

    /**
     * AwaitingApproval → Completed: zatwierdzone z wynikiem.
     */
    public function approve(Outcome $outcome, PartySignature $approver): void
    {
        $this->transitionTo(OnboardingStepStatus::Completed);
        $this->approvedBy = $approver;
        $this->recordOutcome($outcome);
        $this->status = ActionStatus::Closed;
    }

    /**
     * AwaitingApproval → Failed / InProgress → Failed: krok nie powiódł się.
     */
    public function fail(Outcome $outcome): void
    {
        $this->transitionTo(OnboardingStepStatus::Failed);
        $this->recordOutcome($outcome);
    }

    /**
     * InProgress → OnHold: wstrzymaj krok (np. czekamy na dokumenty).
     */
    public function hold(): void
    {
        $this->transitionTo(OnboardingStepStatus::OnHold);
    }

    /**
     * OnHold → InProgress / Failed → InProgress: wznów pracę.
     */
    public function resume(): void
    {
        $this->transitionTo(OnboardingStepStatus::InProgress);
    }

    /**
     * Failed → Escalated: eskaluj po przekroczeniu SLA.
     */
    public function escalate(): void
    {
        $this->transitionTo(OnboardingStepStatus::Escalated);
        $this->status = ActionStatus::Closed;
    }

    /**
     * Sprawdza actualOutcomes w runtime.
     * Jeśli 'needs_supplement' — pętla zwrotna, nowa Action w tym samym Stage.
     */
    public function requiresSupplement(): bool
    {
        return $this->hasActualOutcome(StepResult::NEEDS_SUPPLEMENT);
    }

    public function isAccepted(): bool
    {
        return $this->hasActualOutcome(StepResult::ACCEPTED);
    }

    public function isRejected(): bool
    {
        return $this->hasActualOutcome(StepResult::REJECTED);
    }

    public function isTerminal(): bool
    {
        return $this->stepStatus->isTerminal();
    }

    /** @param array<string, mixed> $context */
    public function withContext(array $context): void
    {
        $this->context = $context;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    private function transitionTo(OnboardingStepStatus $target): void
    {
        if (!$this->stepStatus->canTransitionTo($target)) {
            throw new \DomainException(sprintf(
                'Niedozwolone przejście stanu: %s → %s',
                $this->stepStatus->value,
                $target->value,
            ));
        }

        $this->stepStatus = $target;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
