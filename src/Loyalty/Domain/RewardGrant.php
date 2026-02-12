<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final readonly class RewardGrant
{
    public function __construct(
        public string $rewardId,
        public string $description,
    ) {}
}
