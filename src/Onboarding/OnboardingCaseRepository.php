<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding;

interface OnboardingCaseRepository
{
    public function findById(string $caseId): ?OnboardingCase;
}
