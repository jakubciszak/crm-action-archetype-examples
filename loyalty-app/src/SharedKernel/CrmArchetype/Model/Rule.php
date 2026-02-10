<?php

declare(strict_types=1);

namespace App\SharedKernel\CrmArchetype\Model;

/**
 * Rule — deklaratywna reguła (zamek/klucz).
 *
 * Rule to zamek — Propositions, Variables, Operators, ale BEZ wartości.
 * RuleContext to klucz — te same elementy, ale Z wartościami.
 * Pasuje? Reguła się odpala.
 */
final class Rule
{
    /**
     * @param string $name
     * @param array<string, array{operator: string, threshold: mixed}> $propositions
     */
    public function __construct(
        private readonly string $name,
        private readonly array $propositions,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @param array<string, mixed> $context klucz — wartości zmiennych
     */
    public function evaluate(array $context): bool
    {
        foreach ($this->propositions as $variable => $condition) {
            if (!array_key_exists($variable, $context)) {
                return false;
            }

            $value = $context[$variable];
            $threshold = $condition['threshold'];

            $result = match ($condition['operator']) {
                '>' => $value > $threshold,
                '>=' => $value >= $threshold,
                '<' => $value < $threshold,
                '<=' => $value <= $threshold,
                '==' => $value == $threshold,
                '===' => $value === $threshold,
                '!=' => $value != $threshold,
                'in' => in_array($value, (array) $threshold, true),
                default => false,
            };

            if (!$result) {
                return false;
            }
        }

        return true;
    }
}
