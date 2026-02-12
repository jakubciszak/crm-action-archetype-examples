<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final readonly class PointsDebitedForReturn
{
    public function __construct(
        public string $participantId,
        public string $sourceRef,
        public string $sourceItemRef,
        public int $points,
        public ?string $label,
    ) {}
}
