<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final readonly class PointsExpired
{
    public function __construct(
        public string $participantId,
        public int $points,
        public string $reason,
    ) {}
}
