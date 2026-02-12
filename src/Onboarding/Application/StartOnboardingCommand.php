<?php

declare(strict_types=1);

namespace Onboarding\Application;

final readonly class StartOnboardingCommand
{
    public function __construct(
        public string $caseId,
        public string $clientName,
        public string $clientType,
    ) {}
}
