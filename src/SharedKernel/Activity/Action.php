<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

use SharedKernel\Activity\Event\DomainEvent;

abstract class Action
{
    private ActionState $state = ActionState::Draft;

    /** @var OutcomeBlueprint[] */
    private array $possibleOutcomes = [];

    /** @var Outcome[] */
    private array $actualOutcomes = [];

    private ?PartySignature $initiator = null;

    /** @var PartySignature[] */
    private array $approvers = [];

    /** @var DomainEvent[] */
    private array $domainEvents = [];

    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly \DateTimeImmutable $createdAt,
    ) {}

    public static function fromBlueprint(string $id, StepBlueprint $blueprint, \DateTimeImmutable $now): static
    {
        $action = new static($id, $blueprint->stepCode, $now);
        $action->possibleOutcomes = $blueprint->possibleOutcomes;
        return $action;
    }

    public function transitionTo(ActionState $target): ActionState
    {
        if (!$this->state->canTransitionTo($target)) {
            throw new \DomainException("Cannot transition from {$this->state->value} to {$target->value}");
        }
        $previousState = $this->state;
        $this->state = $target;
        return $previousState;
    }

    public function recordOutcome(Outcome $outcome): void
    {
        $blueprint = $this->findOutcomeBlueprint($outcome->code);
        if ($blueprint === null) {
            throw new \DomainException("Outcome '{$outcome->code}' is not in possibleOutcomes");
        }
        $this->actualOutcomes[] = $outcome;
    }

    public function complete(Outcome ...$outcomes): OutcomeDirectiveSet
    {
        foreach ($outcomes as $o) {
            $this->recordOutcome($o);
        }
        $this->transitionTo(ActionState::Completed);

        $directives = [];
        foreach ($outcomes as $o) {
            $bp = $this->findOutcomeBlueprint($o->code);
            $directives[] = $bp->directive;
        }
        return new OutcomeDirectiveSet($directives);
    }

    /** @return DomainEvent[] */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    protected function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function state(): ActionState
    {
        return $this->state;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return OutcomeBlueprint[] */
    public function possibleOutcomes(): array
    {
        return $this->possibleOutcomes;
    }

    /** @return Outcome[] */
    public function actualOutcomes(): array
    {
        return $this->actualOutcomes;
    }

    public function initiator(): ?PartySignature
    {
        return $this->initiator;
    }

    public function setInitiator(PartySignature $initiator): void
    {
        $this->initiator = $initiator;
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

    private function findOutcomeBlueprint(string $code): ?OutcomeBlueprint
    {
        foreach ($this->possibleOutcomes as $bp) {
            if ($bp->code === $code) {
                return $bp;
            }
        }
        return null;
    }
}
