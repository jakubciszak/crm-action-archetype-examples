<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Event;

interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
