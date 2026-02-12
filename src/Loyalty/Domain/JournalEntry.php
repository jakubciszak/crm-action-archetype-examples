<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final readonly class JournalEntry
{
    public function __construct(
        public int $points,
        public string $reason,
    ) {}
}
