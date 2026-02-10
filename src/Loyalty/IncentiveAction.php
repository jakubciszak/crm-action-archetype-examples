<?php

declare(strict_types=1);

namespace CrmArchetype\Loyalty;

/**
 * Composition instead of inheritance — same pattern, different state machine (slide 16).
 * Does NOT extend Action. Has its own 6-state lifecycle:
 * Received → Evaluating → AwaitingSettlement → Settled (→ Reversed)
 *                       ↘ Rejected
 */
final class IncentiveAction
{
    private IncentiveActionState $state = IncentiveActionState::Received;

    /** @var IncentiveDecision[] */
    private array $decisions = [];

    /** @var object[] */
    private array $events = [];

    private ?string $rejectionReason = null;
    private ?string $reversalReason = null;

    public function __construct(
        private readonly string $id,
        private readonly string $description,
        private readonly string $memberId,
        private readonly string $category,
    ) {}

    public function evaluate(): void
    {
        $this->transitionTo(IncentiveActionState::Evaluating);
    }

    public function settle(IncentiveDecision $decision): void
    {
        $this->transitionTo(IncentiveActionState::AwaitingSettlement);
        $this->decisions[] = $decision;
        $this->transitionTo(IncentiveActionState::Settled);

        $this->events = array_merge($this->events, $decision->domainEvents);
    }

    public function reject(string $reason): void
    {
        $this->rejectionReason = $reason;
        $this->transitionTo(IncentiveActionState::Rejected);
    }

    public function reverse(string $reason): void
    {
        $this->reversalReason = $reason;
        $this->transitionTo(IncentiveActionState::Reversed);
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

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function state(): IncentiveActionState
    {
        return $this->state;
    }

    /** @return IncentiveDecision[] */
    public function decisions(): array
    {
        return $this->decisions;
    }

    /** @return object[] */
    public function releasedEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function reversalReason(): ?string
    {
        return $this->reversalReason;
    }

    // --- Internal ---

    private function transitionTo(IncentiveActionState $target): void
    {
        if (!$this->state->canTransitionTo($target)) {
            throw InvalidIncentiveStateTransitionException::create($this->state, $target);
        }

        $this->state = $target;
    }
}
