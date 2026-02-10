<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding;

use CrmArchetype\Archetype\CommunicationThread;

final class OnboardingPhase extends CommunicationThread
{
    /** @var OnboardingStep[] */
    private array $steps = [];

    public function addStep(OnboardingStep $step): void
    {
        $this->steps[] = $step;
    }

    /** @return OnboardingStep[] */
    public function steps(): array
    {
        return $this->steps;
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

    public function hasSupplementRequired(): bool
    {
        return array_any(
            $this->steps,
            fn(OnboardingStep $step) => $step->requiresSupplement(),
        );
    }
}
