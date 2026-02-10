<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Application\Command;

final readonly class RecordExternalOutcomeCommand
{
    /**
     * @param array<string, mixed> $vendorPayload
     */
    public function __construct(
        public string $actionId,
        public string $outcomeDescription,
        public ?string $outcomeReason = null,
        public ?string $externalReference = null,
        public array $vendorPayload = [],
    ) {}
}
