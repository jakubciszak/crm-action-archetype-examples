<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding;

use CrmArchetype\Archetype\CustomerServiceCase;

final class OnboardingCase extends CustomerServiceCase
{
    /** @var OnboardingPhase[] */
    private array $phases = [];

    public function addPhase(OnboardingPhase $phase): void
    {
        $this->phases[] = $phase;
        $this->addThread($phase);
    }

    /** @return OnboardingPhase[] */
    public function phases(): array
    {
        return $this->phases;
    }

    public function currentPhase(): ?OnboardingPhase
    {
        foreach ($this->phases as $phase) {
            if (!$phase->isClosed()) {
                return $phase;
            }
        }

        return null;
    }

    public function allPhasesCompleted(): bool
    {
        if (empty($this->phases)) {
            return false;
        }

        return array_all(
            $this->phases,
            fn(OnboardingPhase $phase) => $phase->isClosed(),
        );
    }
}
