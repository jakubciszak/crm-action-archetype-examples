<?php

declare(strict_types=1);

namespace Onboarding\Application;

final readonly class RecordExternalOutcomeCommand
{
    public function __construct(
        public string $externalReference,
        public string $outcomeCode,
        public string $outcomeDescription,
        public string $vendor,
    ) {}
}
