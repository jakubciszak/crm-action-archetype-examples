<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

final readonly class Outcome
{
    public function __construct(
        public string $code,
        public string $description,
        public ?string $reason = null,
        public ?PartySignature $approver = null,
        public \DateTimeImmutable $recordedAt = new \DateTimeImmutable(),
    ) {}
}
