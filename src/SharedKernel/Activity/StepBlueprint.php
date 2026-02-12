<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

final readonly class StepBlueprint
{
    /** @param OutcomeBlueprint[] $possibleOutcomes */
    public function __construct(
        public string $stepCode,
        public string $description,
        public array $possibleOutcomes,
    ) {}
}
