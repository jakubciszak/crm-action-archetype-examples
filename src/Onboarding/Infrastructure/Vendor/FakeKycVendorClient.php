<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\Vendor;

final class FakeKycVendorClient implements KycVendorClient
{
    private int $counter = 0;

    public function submitVerification(string $actionId): string
    {
        $this->counter++;
        return 'VRF-' . str_pad((string) $this->counter, 3, '0', STR_PAD_LEFT);
    }
}
