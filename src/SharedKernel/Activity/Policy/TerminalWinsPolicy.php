<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Policy;

use SharedKernel\Activity\DirectiveConflictPolicy;
use SharedKernel\Activity\OutcomeDirective;

final readonly class TerminalWinsPolicy implements DirectiveConflictPolicy
{
    public function resolve(array $directives): array
    {
        $terminals = array_values(array_filter($directives, fn(OutcomeDirective $d) => $d->type->isTerminal()));
        $spawns = array_values(array_filter($directives, fn(OutcomeDirective $d) => $d->type->isComposable()));

        if ($terminals !== []) {
            usort($terminals, fn(OutcomeDirective $a, OutcomeDirective $b) => $a->type->priority() <=> $b->type->priority());
            return [$terminals[0], ...$spawns];
        }

        $nonSpawn = array_values(array_filter($directives, fn(OutcomeDirective $d) => !$d->type->isComposable()));
        if (count($nonSpawn) > 1) {
            usort($nonSpawn, fn(OutcomeDirective $a, OutcomeDirective $b) => $a->type->priority() <=> $b->type->priority());
            return [$nonSpawn[0], ...$spawns];
        }

        return $directives;
    }
}
