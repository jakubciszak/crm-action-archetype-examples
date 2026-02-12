<?php

declare(strict_types=1);

namespace CrmArchetype\Rule;

final readonly class Proposition
{
    public function __construct(
        public string $variable,
        public string $operator,
        public mixed $threshold,
    ) {}

    public function evaluate(mixed $actualValue): bool
    {
        return match ($this->operator) {
            '>' => $actualValue > $this->threshold,
            '>=' => $actualValue >= $this->threshold,
            '<' => $actualValue < $this->threshold,
            '<=' => $actualValue <= $this->threshold,
            '==' => $actualValue == $this->threshold,
            '===' => $actualValue === $this->threshold,
            '!=' => $actualValue != $this->threshold,
            default => throw new \InvalidArgumentException("Unknown operator: {$this->operator}"),
        };
    }
}
