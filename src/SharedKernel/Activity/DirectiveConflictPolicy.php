<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

interface DirectiveConflictPolicy
{
    /** @param OutcomeDirective[] $directives @return OutcomeDirective[] */
    public function resolve(array $directives): array;
}
