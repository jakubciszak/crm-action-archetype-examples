<?php

declare(strict_types=1);

namespace Onboarding\Domain;

use SharedKernel\Activity\StageBlueprint;

final class OnboardingStage
{
    /** @var OnboardingStep[] */
    private array $steps = [];
    private bool $completed = false;

    public function __construct(
        private readonly string $stageCode,
        private readonly string $description,
    ) {}

    public static function fromBlueprint(StageBlueprint $blueprint): self
    {
        return new self($blueprint->stageCode, $blueprint->description);
    }

    public function stageCode(): string
    {
        return $this->stageCode;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function addStep(OnboardingStep $step): void
    {
        $this->steps[] = $step;
    }

    /** @return OnboardingStep[] */
    public function steps(): array
    {
        return $this->steps;
    }

    public function findStep(string $stepCode): ?OnboardingStep
    {
        foreach ($this->steps as $step) {
            if ($step->type() === $stepCode) {
                return $step;
            }
        }
        return null;
    }

    public function allStepsCompleted(): bool
    {
        if (empty($this->steps)) {
            return false;
        }

        return array_all(
            $this->steps,
            fn(OnboardingStep $step) => $step->state()->isTerminal(),
        );
    }

    public function markCompleted(): void
    {
        $this->completed = true;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }
}
