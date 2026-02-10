<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding;

use CrmArchetype\Archetype\Action;
use CrmArchetype\Archetype\Outcome;
use CrmArchetype\Archetype\PartySignature;

final class OnboardingStep extends Action
{
    public static function fromBlueprint(
        StepBlueprint $blueprint,
        PartySignature $initiator,
    ): self {
        return new self(
            id: $blueprint->stepCode,
            description: $blueprint->description,
            initiator: $initiator,
            possibleOutcomes: array_map(
                fn(string $desc) => new Outcome($desc),
                $blueprint->possibleOutcomes,
            ),
        );
    }

    public function requiresSupplement(): bool
    {
        return array_any(
            $this->actualOutcomes(),
            fn(Outcome $o) => $o->description === 'DoUzupelnienia',
        );
    }

    public function isAccepted(): bool
    {
        return array_any(
            $this->actualOutcomes(),
            fn(Outcome $o) => $o->description === 'Zaakceptowany',
        );
    }

    public function isRejected(): bool
    {
        return array_any(
            $this->actualOutcomes(),
            fn(Outcome $o) => $o->description === 'Odrzucony',
        );
    }
}
