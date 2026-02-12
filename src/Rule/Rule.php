<?php

declare(strict_types=1);

namespace CrmArchetype\Rule;

final class Rule
{
    /**
     * @param Proposition[] $propositions
     */
    public function __construct(
        private readonly string $name,
        private readonly array $propositions,
    ) {}

    public function evaluate(RuleContext $context): bool
    {
        foreach ($this->propositions as $proposition) {
            $value = $context->get($proposition->variable);

            if (!$proposition->evaluate($value)) {
                return false;
            }
        }

        return true;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return Proposition[] */
    public function propositions(): array
    {
        return $this->propositions;
    }
}
