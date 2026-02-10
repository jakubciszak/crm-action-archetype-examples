<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Model;

/**
 * Przyznanie nagrody (kupon, zniżka, benefit).
 */
final class RewardGrant
{
    public function __construct(
        private readonly string $memberId,
        private readonly string $rewardCode,
        private readonly string $description,
        private readonly \DateTimeImmutable $grantedAt = new \DateTimeImmutable(),
    ) {
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function rewardCode(): string
    {
        return $this->rewardCode;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function grantedAt(): \DateTimeImmutable
    {
        return $this->grantedAt;
    }
}
