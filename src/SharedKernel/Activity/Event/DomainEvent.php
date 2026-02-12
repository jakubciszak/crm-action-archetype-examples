<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Event;

interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
