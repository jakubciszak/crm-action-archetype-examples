<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final readonly class PointsDebited
{
    public function __construct(
        public string $participantId,
        public JournalEntry $entry,
        public string $reason,
    ) {}
}
