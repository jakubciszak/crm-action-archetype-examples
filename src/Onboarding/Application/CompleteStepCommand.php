<?php

declare(strict_types=1);

namespace Onboarding\Application;

use SharedKernel\Activity\PartySignature;

final readonly class CompleteStepCommand
{
    public function __construct(
        public string $caseId,
        public string $stageCode,
        public string $stepCode,
        public string $outcomeCode,
        public string $outcomeDescription,
        public ?PartySignature $approver = null,
    ) {}
}
