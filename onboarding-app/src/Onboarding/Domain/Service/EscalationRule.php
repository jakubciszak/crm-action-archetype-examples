<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Service;

use App\SharedKernel\CrmArchetype\Model\Rule;

/**
 * Reguła eskalacji KYC: IF kycDays > 5 AND isEnterprise THEN → State = Escalated.
 * Reguły jako dane, nie kod. Zmiana progu = update w bazie, nie redeploy.
 */
final class EscalationRule
{
    private Rule $rule;

    public function __construct(int $kycDaysThreshold = 5)
    {
        $this->rule = new Rule('escalate_kyc', [
            'kyc_days' => ['operator' => '>', 'threshold' => $kycDaysThreshold],
            'is_enterprise' => ['operator' => '===', 'threshold' => true],
        ]);
    }

    /**
     * @param array<string, mixed> $context ['kyc_days' => int, 'is_enterprise' => bool]
     */
    public function shouldEscalate(array $context): bool
    {
        return $this->rule->evaluate($context);
    }

    public function rule(): Rule
    {
        return $this->rule;
    }
}
