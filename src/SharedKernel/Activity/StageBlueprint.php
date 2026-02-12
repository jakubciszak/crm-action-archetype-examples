<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

final readonly class StageBlueprint
{
    /** @param StepBlueprint[] $steps */
    public function __construct(
        public string $stageCode,
        public string $description,
        public array $steps,
    ) {}
}
