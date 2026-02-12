<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final readonly class PointsDebitedForReturn
{
    public function __construct(
        public string $participantId,
        public string $orderId,
        public string $lineId,
        public int $points,
        public ?string $productName,
    ) {}
}
