<?php

declare(strict_types=1);

namespace CrmArchetype\Loyalty;

final readonly class JournalEntry
{
    public function __construct(
        public string $accountId,
        public int $points,
        public string $description,
    ) {}
}
