<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Model;

/**
 * ActionOccurred — Communication w domenie loyalty.
 * Event: zakup, check-in, polecenie, recenzja.
 *
 * Każde zdarzenie jest niezależne (event-driven, nie sekwencyjne).
 */
final class ActionOccurred
{
    /** @var IncentiveAction[] */
    private array $incentiveActions = [];
    private \DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $eventData
     */
    public function __construct(
        private readonly string $id,
        private readonly string $memberId,
        private readonly string $eventType,
        private readonly array $eventData = [],
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    /** @return array<string, mixed> */
    public function eventData(): array
    {
        return $this->eventData;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function addIncentiveAction(IncentiveAction $action): void
    {
        $this->incentiveActions[] = $action;
    }

    /** @return IncentiveAction[] */
    public function incentiveActions(): array
    {
        return $this->incentiveActions;
    }
}
