<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Infrastructure\Vendor;

interface KycVendorClient
{
    /**
     * @param string[] $documentIds
     */
    public function submitVerification(array $documentIds, string $callbackUrl): KycSubmissionResult;
}
