<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Service;

use App\Loyalty\Domain\Model\CategoryType;

/**
 * Kalkulator punktów lojalnościowych.
 * Bazowa logika: 1 PLN = 1 punkt. Mnożniki per kategoria.
 */
final class PointsCalculator
{
    /** @var array<string, float> */
    private array $multipliers;

    /**
     * @param array<string, float> $multipliers mnożniki per kategoria
     */
    public function __construct(array $multipliers = [])
    {
        $this->multipliers = $multipliers ?: [
            CategoryType::Purchases->value => 1.0,
            CategoryType::Referrals->value => 2.0,
            CategoryType::Reviews->value => 0.5,
        ];
    }

    public function calculate(CategoryType $category, float $baseAmount): int
    {
        $multiplier = $this->multipliers[$category->value] ?? 1.0;

        return (int) floor($baseAmount * $multiplier);
    }

    public function calculateWithBonus(CategoryType $category, float $baseAmount, float $bonusMultiplier): int
    {
        return (int) floor($this->calculate($category, $baseAmount) * $bonusMultiplier);
    }
}
