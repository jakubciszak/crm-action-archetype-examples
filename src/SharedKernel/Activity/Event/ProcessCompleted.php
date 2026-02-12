<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Event;

final readonly class ProcessCompleted implements DomainEvent
{
    public function __construct(
        public string $caseId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
