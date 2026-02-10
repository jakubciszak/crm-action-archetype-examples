<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Event;

final class StepCompleted implements DomainEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $caseId,
        public readonly string $stageId,
        public readonly string $stepId,
        public readonly string $outcomeCode,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
