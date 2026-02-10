<?php

declare(strict_types=1);

namespace Tests\Onboarding;

use App\Onboarding\Domain\Service\EscalationRule;
use PHPUnit\Framework\TestCase;

/**
 * Chicago-style: testujemy regułę eskalacji przez publiczne API.
 * State + Outcome + Rule = pełna kontrola.
 */
final class EscalationRuleTest extends TestCase
{
    public function test_escalation_triggered_for_enterprise_over_threshold(): void
    {
        $rule = new EscalationRule(kycDaysThreshold: 5);

        $result = $rule->shouldEscalate([
            'kyc_days' => 7,
            'is_enterprise' => true,
        ]);

        self::assertTrue($result);
    }

    public function test_no_escalation_for_enterprise_within_threshold(): void
    {
        $rule = new EscalationRule(kycDaysThreshold: 5);

        $result = $rule->shouldEscalate([
            'kyc_days' => 3,
            'is_enterprise' => true,
        ]);

        self::assertFalse($result);
    }

    public function test_no_escalation_for_sme_even_over_threshold(): void
    {
        $rule = new EscalationRule(kycDaysThreshold: 5);

        $result = $rule->shouldEscalate([
            'kyc_days' => 10,
            'is_enterprise' => false,
        ]);

        self::assertFalse($result);
    }

    public function test_no_escalation_when_context_incomplete(): void
    {
        $rule = new EscalationRule(kycDaysThreshold: 5);

        $result = $rule->shouldEscalate([
            'kyc_days' => 10,
            // brak is_enterprise
        ]);

        self::assertFalse($result);
    }

    public function test_custom_threshold(): void
    {
        $rule = new EscalationRule(kycDaysThreshold: 10);

        self::assertFalse($rule->shouldEscalate(['kyc_days' => 7, 'is_enterprise' => true]));
        self::assertTrue($rule->shouldEscalate(['kyc_days' => 11, 'is_enterprise' => true]));
    }
}
