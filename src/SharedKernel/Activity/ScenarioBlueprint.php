<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

final readonly class ScenarioBlueprint
{
    /** @param StageBlueprint[] $stages */
    public function __construct(
        public string $scenarioCode,
        public string $description,
        public array $stages,
        public DirectiveConflictPolicy $conflictPolicy,
    ) {}
}
