<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure;

use Onboarding\Domain\OnboardingCase;

final class InMemoryOnboardingCaseRepository
{
    /** @var array<string, OnboardingCase> */
    private array $cases = [];

    public function save(OnboardingCase $case): void
    {
        $this->cases[$case->id()] = $case;
    }

    public function findById(string $id): ?OnboardingCase
    {
        return $this->cases[$id] ?? null;
    }
}
