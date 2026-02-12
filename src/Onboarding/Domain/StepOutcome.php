<?php

declare(strict_types=1);

namespace Onboarding\Domain;

use SharedKernel\Activity\Outcome;
use SharedKernel\Activity\PartySignature;

final readonly class StepOutcome extends Outcome
{
    public function __construct(
        string $code,
        string $description,
        ?string $reason = null,
        ?PartySignature $approver = null,
        \DateTimeImmutable $recordedAt = new \DateTimeImmutable(),
    ) {
        parent::__construct($code, $description, $reason, $approver, $recordedAt);
    }
}
