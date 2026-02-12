<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\Vendor;

interface KycVendorClient
{
    public function submitVerification(string $actionId): string;
}
