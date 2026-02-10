<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Service;

use App\SharedKernel\CrmArchetype\Model\Rule;

/**
 * Reguła podwójnych punktów: IF isGoldMember AND orderPLN > 500 THEN 2x points.
 * Reguły jako dane, nie kod. Zmiana progu = update w bazie, nie redeploy.
 */
final class DoublePointsRule
{
    private Rule $rule;
    private float $bonusMultiplier;

    public function __construct(float $orderThreshold = 500.0, float $bonusMultiplier = 2.0)
    {
        $this->bonusMultiplier = $bonusMultiplier;
        $this->rule = new Rule('double_points', [
            'order_pln' => ['operator' => '>', 'threshold' => $orderThreshold],
            'is_gold_member' => ['operator' => '===', 'threshold' => true],
        ]);
    }

    /**
     * @param array<string, mixed> $context ['order_pln' => float, 'is_gold_member' => bool]
     */
    public function applies(array $context): bool
    {
        return $this->rule->evaluate($context);
    }

    public function bonusMultiplier(): float
    {
        return $this->bonusMultiplier;
    }

    public function rule(): Rule
    {
        return $this->rule;
    }
}
