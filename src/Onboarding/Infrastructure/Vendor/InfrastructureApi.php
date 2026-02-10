<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Infrastructure\Vendor;

interface InfrastructureApi
{
    /**
     * @throws ProvisioningQueuedException when provisioning is async
     */
    public function provisionTenant(string $clientId, string $tier): ProvisioningResult;
}
