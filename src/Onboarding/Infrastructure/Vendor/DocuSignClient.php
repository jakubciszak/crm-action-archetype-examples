<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Infrastructure\Vendor;

interface DocuSignClient
{
    /**
     * @param string[] $signers
     */
    public function sendForSignature(string $templateId, array $signers, string $callbackUrl): DocuSignEnvelopeResult;
}
