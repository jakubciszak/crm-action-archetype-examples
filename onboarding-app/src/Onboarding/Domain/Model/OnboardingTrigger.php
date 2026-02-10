<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Model;

use App\SharedKernel\CrmArchetype\Model\Communication;

/**
 * OnboardingTrigger — Communication w domenie onboardingu.
 * Trigger: wniosek klienta, event systemowy, lub pętla zwrotna (supplement).
 */
class OnboardingTrigger extends Communication
{
    public function __construct(
        string $id,
        string $source,
        private readonly string $triggerType,
    ) {
        parent::__construct($id, $source);
    }

    public function triggerType(): string
    {
        return $this->triggerType;
    }

    public static function customerRequest(string $id): self
    {
        return new self($id, 'customer', 'customer_request');
    }

    public static function systemEvent(string $id, string $eventName): self
    {
        return new self($id, 'system', $eventName);
    }

    public static function supplementRequest(string $id): self
    {
        return new self($id, 'system', 'supplement_request');
    }
}
