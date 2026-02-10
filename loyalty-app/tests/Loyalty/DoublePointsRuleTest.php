<?php

declare(strict_types=1);

namespace Tests\Loyalty;

use App\Loyalty\Domain\Service\DoublePointsRule;
use App\Loyalty\Domain\Service\PointsCalculator;
use App\Loyalty\Domain\Model\CategoryType;
use PHPUnit\Framework\TestCase;

/**
 * Chicago-style: testujemy regułę i kalkulator przez publiczne API.
 * State + Outcome + Rule = pełna kontrola.
 */
final class DoublePointsRuleTest extends TestCase
{
    // --- DoublePointsRule ---

    public function test_rule_applies_for_gold_member_over_threshold(): void
    {
        $rule = new DoublePointsRule(orderThreshold: 500.0);

        self::assertTrue($rule->applies([
            'order_pln' => 600.0,
            'is_gold_member' => true,
        ]));
    }

    public function test_rule_does_not_apply_for_regular_member(): void
    {
        $rule = new DoublePointsRule(orderThreshold: 500.0);

        self::assertFalse($rule->applies([
            'order_pln' => 600.0,
            'is_gold_member' => false,
        ]));
    }

    public function test_rule_does_not_apply_under_threshold(): void
    {
        $rule = new DoublePointsRule(orderThreshold: 500.0);

        self::assertFalse($rule->applies([
            'order_pln' => 300.0,
            'is_gold_member' => true,
        ]));
    }

    public function test_custom_threshold_and_multiplier(): void
    {
        $rule = new DoublePointsRule(orderThreshold: 1000.0, bonusMultiplier: 3.0);

        self::assertSame(3.0, $rule->bonusMultiplier());
        self::assertTrue($rule->applies([
            'order_pln' => 1500.0,
            'is_gold_member' => true,
        ]));
    }

    // --- PointsCalculator ---

    public function test_purchase_base_points(): void
    {
        $calculator = new PointsCalculator();

        self::assertSame(100, $calculator->calculate(CategoryType::Purchases, 100.0));
    }

    public function test_referral_double_multiplier(): void
    {
        $calculator = new PointsCalculator();

        // Referrals mają 2.0x mnożnik
        self::assertSame(200, $calculator->calculate(CategoryType::Referrals, 100.0));
    }

    public function test_review_half_multiplier(): void
    {
        $calculator = new PointsCalculator();

        // Reviews mają 0.5x mnożnik
        self::assertSame(50, $calculator->calculate(CategoryType::Reviews, 100.0));
    }

    public function test_calculate_with_bonus(): void
    {
        $calculator = new PointsCalculator();

        // 100 PLN * 1.0 (purchases) * 2.0 (bonus) = 200
        self::assertSame(200, $calculator->calculateWithBonus(CategoryType::Purchases, 100.0, 2.0));
    }

    public function test_custom_multipliers(): void
    {
        $calculator = new PointsCalculator([
            'purchases' => 1.5,
            'referrals' => 3.0,
            'reviews' => 1.0,
        ]);

        self::assertSame(150, $calculator->calculate(CategoryType::Purchases, 100.0));
        self::assertSame(300, $calculator->calculate(CategoryType::Referrals, 100.0));
    }
}
