<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final readonly class RewardGranted
{
    public function __construct(
        public string $participantId,
        public RewardGrant $grant,
    ) {}
}
