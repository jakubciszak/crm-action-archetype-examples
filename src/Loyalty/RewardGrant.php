<?php

declare(strict_types=1);

namespace CrmArchetype\Loyalty;

final readonly class RewardGrant
{
    public function __construct(
        public string $rewardId,
        public string $memberId,
        public string $description,
    ) {}
}
