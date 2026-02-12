<?php

declare(strict_types=1);

namespace Onboarding\Domain;

use SharedKernel\Activity\Action;
use SharedKernel\Activity\Outcome;

final class OnboardingStep extends Action
{
    public function requiresSupplement(): bool
    {
        return array_any(
            $this->actualOutcomes(),
            fn(Outcome $o) => $o->code === 'needs_supplement'
        );
    }

    public function isRejected(): bool
    {
        return array_any(
            $this->actualOutcomes(),
            fn(Outcome $o) => $o->code === 'rejected'
        );
    }
}
