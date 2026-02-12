<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Event;

final readonly class StepCompleted implements DomainEvent
{
    public function __construct(
        public string $caseId,
        public string $stageCode,
        public string $stepCode,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
