<?php

declare(strict_types=1);

namespace CrmArchetype\Rule;

final class RuleContext
{
    /** @var array<string, mixed> */
    private array $variables = [];

    public function set(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;

        return $this;
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->variables)) {
            throw new \InvalidArgumentException("Variable '{$key}' not found in context.");
        }

        return $this->variables[$key];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->variables);
    }
}
