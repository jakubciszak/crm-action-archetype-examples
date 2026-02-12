<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Policy;

use SharedKernel\Activity\DirectiveConflictPolicy;
use SharedKernel\Activity\OutcomeDirective;

final readonly class EscalateOnConflictPolicy implements DirectiveConflictPolicy
{
    public function resolve(array $directives): array
    {
        $nonComposable = array_filter($directives, fn(OutcomeDirective $d) => !$d->type->isComposable());

        if (count($nonComposable) > 1) {
            return [OutcomeDirective::escalate()];
        }

        return $directives;
    }
}
