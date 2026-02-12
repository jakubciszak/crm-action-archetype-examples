<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

final readonly class OutcomeBlueprint
{
    public function __construct(
        public string $code,
        public string $description,
        public OutcomeDirective $directive,
    ) {}
}
