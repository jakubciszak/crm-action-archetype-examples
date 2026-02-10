<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Infrastructure\Vendor;

final readonly class KycSubmissionResult
{
    public function __construct(
        public string $verificationId,
    ) {}
}
