<?php

declare(strict_types=1);

namespace CrmArchetype\Rule;

/**
 * ActivityRule = lock that opens a door automatically.
 * evaluate() returns true → activity() is called (slide 23).
 */
abstract class ActivityRule
{
    abstract public function rule(): Rule;

    abstract protected function activity(): mixed;

    public function evaluate(RuleContext $context): bool
    {
        $result = $this->rule()->evaluate($context);

        if ($result) {
            $this->activity();
        }

        return $result;
    }
}
