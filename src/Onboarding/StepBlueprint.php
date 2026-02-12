<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding;

final readonly class StepBlueprint
{
    /**
     * @param string[] $possibleOutcomes
     */
    public function __construct(
        public string $stepCode,
        public string $description,
        public array $possibleOutcomes,
    ) {}
}
