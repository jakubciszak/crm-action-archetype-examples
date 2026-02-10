<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Event;

interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
