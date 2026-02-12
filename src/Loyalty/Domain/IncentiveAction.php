<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final class IncentiveAction
{
    private IncentiveActionState $state = IncentiveActionState::Received;
    private ?IncentiveDecision $decision = null;

    /** @var object[] */
    private array $domainEvents = [];

    public function __construct(
        private readonly string $id,
        private readonly string $actionType,
        private readonly array $payload,
        private readonly string $participantId,
        private readonly \DateTimeImmutable $occurredAt,
    ) {}

    public function evaluate(IncentiveRule ...$rules): void
    {
        $this->transitionTo(IncentiveActionState::Evaluating);

        foreach ($rules as $rule) {
            if ($rule->supports($this->actionType)) {
                $this->decision = $rule->evaluate($this);
                break;
            }
        }

        if ($this->decision === null) {
            $this->transitionTo(IncentiveActionState::Rejected);
            return;
        }

        $this->transitionTo(IncentiveActionState::AwaitingSettlement);
    }

    public function settle(): void
    {
        if ($this->decision === null) {
            throw new \DomainException('Cannot settle without decision');
        }
        $this->transitionTo(IncentiveActionState::Settled);

        foreach ($this->decision->journalEntries as $entry) {
            $this->domainEvents[] = new PointsGranted($this->participantId, $entry);
        }
        foreach ($this->decision->rewardGrants as $grant) {
            $this->domainEvents[] = new RewardGranted($this->participantId, $grant);
        }
    }

    public function reverse(string $reason): void
    {
        if ($this->state !== IncentiveActionState::Settled) {
            throw new \DomainException('Can only reverse settled actions');
        }
        $this->transitionTo(IncentiveActionState::Reversed);

        foreach ($this->decision->journalEntries as $entry) {
            $this->domainEvents[] = new PointsDebited($this->participantId, $entry, $reason);
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function actionType(): string
    {
        return $this->actionType;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function participantId(): string
    {
        return $this->participantId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function state(): IncentiveActionState
    {
        return $this->state;
    }

    public function decision(): ?IncentiveDecision
    {
        return $this->decision;
    }

    /** @return object[] */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function transitionTo(IncentiveActionState $target): void
    {
        if (!$this->state->canTransitionTo($target)) {
            throw new \DomainException("Cannot transition from {$this->state->value} to {$target->value}");
        }
        $this->state = $target;
    }
}
