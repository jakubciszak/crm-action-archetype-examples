<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Infrastructure\Vendor;

final class ProvisioningQueuedException extends \RuntimeException
{
    public function __construct(
        public readonly string $jobId,
    ) {
        parent::__construct(sprintf('Provisioning queued with job ID: %s', $jobId));
    }
}
