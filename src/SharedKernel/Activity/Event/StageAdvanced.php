<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Event;

final readonly class StageAdvanced implements DomainEvent
{
    public function __construct(
        public string $caseId,
        public string $fromStageCode,
        public string $toStageCode,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
