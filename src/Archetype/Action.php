<?php

declare(strict_types=1);

namespace CrmArchetype\Archetype;

class Action
{
    private ActionState $state = ActionState::Draft;

    /** @var Outcome[] */
    private array $actualOutcomes = [];

    /** @var PartySignature[] */
    private array $approvers = [];

    private ?\DateTimeImmutable $start = null;
    private ?\DateTimeImmutable $end = null;

    /**
     * @param Outcome[] $possibleOutcomes
     */
    public function __construct(
        private readonly string $id,
        private readonly string $description,
        private readonly ?PartySignature $initiator = null,
        private readonly array $possibleOutcomes = [],
        private ?string $reason = null,
    ) {}

    // --- State transitions (slide 19) ---

    public function submit(): void
    {
        $this->transitionTo(ActionState::Pending);
    }

    public function start(): void
    {
        $this->transitionTo(ActionState::InProgress);
        $this->start = new \DateTimeImmutable();
    }

    public function requestApproval(): void
    {
        $this->transitionTo(ActionState::AwaitingApproval);
    }

    public function complete(Outcome $outcome): void
    {
        $this->transitionTo(ActionState::Completed);
        $this->recordOutcome($outcome);
        $this->end = new \DateTimeImmutable();
    }

    public function approve(Outcome $outcome): void
    {
        $this->transitionTo(ActionState::Completed);
        $this->recordOutcome($outcome);
        $this->end = new \DateTimeImmutable();
    }

    public function rejectApproval(): void
    {
        $this->transitionTo(ActionState::InProgress);
    }

    public function hold(): void
    {
        $this->transitionTo(ActionState::OnHold);
    }

    public function resume(): void
    {
        if ($this->state !== ActionState::OnHold) {
            throw InvalidStateTransitionException::create($this->state, ActionState::InProgress);
        }

        $this->transitionTo(ActionState::InProgress);
    }

    public function fail(string $reason): void
    {
        $this->reason = $reason;
        $this->transitionTo(ActionState::Failed);
    }

    public function retry(): void
    {
        $this->transitionTo(ActionState::InProgress);
    }

    public function escalate(): void
    {
        $this->transitionTo(ActionState::Escalated);
    }

    // --- Outcome management ---

    public function recordOutcome(Outcome $outcome): void
    {
        $this->actualOutcomes[] = $outcome;
    }

    /** @return Outcome[] */
    public function possibleOutcomes(): array
    {
        return $this->possibleOutcomes;
    }

    /** @return Outcome[] */
    public function actualOutcomes(): array
    {
        return $this->actualOutcomes;
    }

    // --- Accessors ---

    public function id(): string
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function state(): ActionState
    {
        return $this->state;
    }

    public function initiator(): ?PartySignature
    {
        return $this->initiator;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->start;
    }

    public function endedAt(): ?\DateTimeImmutable
    {
        return $this->end;
    }

    /** @return PartySignature[] */
    public function approvers(): array
    {
        return $this->approvers;
    }

    public function addApprover(PartySignature $approver): void
    {
        $this->approvers[] = $approver;
    }

    // --- Internal ---

    protected function transitionTo(ActionState $target): void
    {
        if (!$this->state->canTransitionTo($target)) {
            throw InvalidStateTransitionException::create($this->state, $target);
        }

        $this->state = $target;
    }
}
