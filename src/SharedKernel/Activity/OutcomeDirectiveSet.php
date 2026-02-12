<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

final readonly class OutcomeDirectiveSet
{
    /** @param OutcomeDirective[] $directives */
    public function __construct(
        private array $directives,
    ) {}

    /** @return OutcomeDirective[] */
    public function resolve(DirectiveConflictPolicy $policy): array
    {
        return $policy->resolve($this->directives);
    }

    /** @return OutcomeDirective[] */
    public function directives(): array
    {
        return $this->directives;
    }
}
