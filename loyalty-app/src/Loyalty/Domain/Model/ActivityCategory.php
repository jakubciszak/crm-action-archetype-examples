<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Model;

/**
 * ActivityCategory — CommunicationThread w domenie loyalty.
 * Kategorie: Zakupy, Polecenia, Recenzje.
 */
final class ActivityCategory
{
    /** @var ActionOccurred[] */
    private array $events = [];

    public function __construct(
        private readonly string $id,
        private readonly CategoryType $type,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): CategoryType
    {
        return $this->type;
    }

    public function recordEvent(ActionOccurred $event): void
    {
        $this->events[] = $event;
    }

    /** @return ActionOccurred[] */
    public function events(): array
    {
        return $this->events;
    }

    public function totalSettledIncentives(): int
    {
        $count = 0;
        foreach ($this->events as $event) {
            foreach ($event->incentiveActions() as $action) {
                if ($action->hasOutcome(IncentiveDecision::POINTS_GRANTED)
                    || $action->hasOutcome(IncentiveDecision::REWARD_GRANT)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
