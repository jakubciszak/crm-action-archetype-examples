<?php

declare(strict_types=1);

namespace Onboarding\Domain;

use SharedKernel\Activity\ActionState;
use SharedKernel\Activity\DirectiveConflictPolicy;
use SharedKernel\Activity\Event\DomainEvent;
use SharedKernel\Activity\Event\ProcessCompleted;
use SharedKernel\Activity\Event\StageAdvanced;
use SharedKernel\Activity\Event\StepCompleted;
use SharedKernel\Activity\Outcome;
use SharedKernel\Activity\OutcomeDirective;
use SharedKernel\Activity\OutcomeDirectiveType;
use SharedKernel\Activity\PartySignature;
use SharedKernel\Activity\ScenarioBlueprint;
use SharedKernel\Activity\StageBlueprint;
use SharedKernel\Activity\StepBlueprint;

final class OnboardingCase
{
    /** @var OnboardingStage[] */
    private array $stages = [];
    private int $currentStageIndex = 0;
    private OnboardingState $state = OnboardingState::Draft;
    private DirectiveConflictPolicy $conflictPolicy;

    /** @var DomainEvent[] */
    private array $domainEvents = [];

    public function __construct(
        private readonly string $id,
        private readonly string $clientName,
        private readonly string $clientType,
    ) {}

    public static function fromScenario(string $id, string $clientName, string $clientType, ScenarioBlueprint $scenario): self
    {
        $case = new self($id, $clientName, $clientType);
        $case->conflictPolicy = $scenario->conflictPolicy;

        foreach ($scenario->stages as $stageBlueprint) {
            $stage = OnboardingStage::fromBlueprint($stageBlueprint);
            foreach ($stageBlueprint->steps as $stepBlueprint) {
                $step = OnboardingStep::fromBlueprint(
                    id: $id . '_' . $stepBlueprint->stepCode,
                    blueprint: $stepBlueprint,
                    now: new \DateTimeImmutable(),
                );
                $stage->addStep($step);
            }
            $case->stages[] = $stage;
        }

        $case->state = OnboardingState::Pending;
        return $case;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function clientName(): string
    {
        return $this->clientName;
    }

    public function clientType(): string
    {
        return $this->clientType;
    }

    public function state(): OnboardingState
    {
        return $this->state;
    }

    /** @return OnboardingStage[] */
    public function stages(): array
    {
        return $this->stages;
    }

    public function currentStage(): ?OnboardingStage
    {
        return $this->stages[$this->currentStageIndex] ?? null;
    }

    public function startStep(string $stageCode, string $stepCode): void
    {
        $stage = $this->findStage($stageCode);
        if ($stage === null) {
            throw new \DomainException("Stage '{$stageCode}' not found");
        }

        $step = $stage->findStep($stepCode);
        if ($step === null) {
            throw new \DomainException("Step '{$stepCode}' not found in stage '{$stageCode}'");
        }

        $step->transitionTo(ActionState::Pending);
        $step->transitionTo(ActionState::InProgress);
        $this->state = OnboardingState::InProgress;
    }

    /** @return OutcomeDirective[] */
    public function completeStep(string $stageCode, string $stepCode, Outcome $outcome, ?PartySignature $approver = null): array
    {
        $stage = $this->findStage($stageCode);
        if ($stage === null) {
            throw new \DomainException("Stage '{$stageCode}' not found");
        }

        $step = $stage->findStep($stepCode);
        if ($step === null) {
            throw new \DomainException("Step '{$stepCode}' not found in stage '{$stageCode}'");
        }

        if ($approver !== null) {
            $step->addApprover($approver);
        }

        $directiveSet = $step->complete($outcome);
        $resolvedDirectives = $directiveSet->resolve($this->conflictPolicy);

        $this->domainEvents[] = new StepCompleted($this->id, $stageCode, $stepCode);

        foreach ($resolvedDirectives as $directive) {
            $this->applyDirective($directive, $stageCode);
        }

        return $resolvedDirectives;
    }

    public function advanceStage(string $stageCode): void
    {
        $stage = $this->findStage($stageCode);
        if ($stage === null) {
            throw new \DomainException("Stage '{$stageCode}' not found");
        }

        $stage->markCompleted();

        if ($this->currentStageIndex < count($this->stages) - 1) {
            $fromCode = $this->stages[$this->currentStageIndex]->stageCode();
            $this->currentStageIndex++;
            $toCode = $this->stages[$this->currentStageIndex]->stageCode();
            $this->domainEvents[] = new StageAdvanced($this->id, $fromCode, $toCode);
        }
    }

    public function isComplete(): bool
    {
        return $this->state === OnboardingState::Completed;
    }

    /** @return DomainEvent[] */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function findStage(string $stageCode): ?OnboardingStage
    {
        foreach ($this->stages as $stage) {
            if ($stage->stageCode() === $stageCode) {
                return $stage;
            }
        }
        return null;
    }

    private function applyDirective(OutcomeDirective $directive, string $currentStageCode): void
    {
        match ($directive->type) {
            OutcomeDirectiveType::AdvanceStage => $this->advanceStage($currentStageCode),
            OutcomeDirectiveType::CompleteProcess => $this->completeProcess(),
            OutcomeDirectiveType::FailProcess => $this->failProcess($directive->params['reason'] ?? 'Unknown'),
            OutcomeDirectiveType::Escalate => $this->escalateProcess(),
            OutcomeDirectiveType::Hold => $this->holdProcess($directive->params['reason'] ?? 'Unknown'),
            OutcomeDirectiveType::RetryStep, OutcomeDirectiveType::SpawnStep => null,
        };
    }

    private function completeProcess(): void
    {
        $this->state = OnboardingState::Completed;
        $this->domainEvents[] = new ProcessCompleted($this->id);
    }

    private function failProcess(string $reason): void
    {
        $this->state = OnboardingState::Failed;
    }

    private function escalateProcess(): void
    {
        $this->state = OnboardingState::Escalated;
    }

    private function holdProcess(string $reason): void
    {
        $this->state = OnboardingState::OnHold;
    }
}
