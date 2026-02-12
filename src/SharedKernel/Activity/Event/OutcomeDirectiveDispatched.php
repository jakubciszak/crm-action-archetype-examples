<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Event;

use SharedKernel\Activity\OutcomeDirective;

final readonly class OutcomeDirectiveDispatched implements DomainEvent
{
    public function __construct(
        public string $caseId,
        public string $stepCode,
        public OutcomeDirective $directive,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
