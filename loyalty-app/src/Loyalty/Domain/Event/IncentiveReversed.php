<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Event;

final class IncentiveReversed implements DomainEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $actionId,
        public readonly string $memberId,
        public readonly string $reason,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
