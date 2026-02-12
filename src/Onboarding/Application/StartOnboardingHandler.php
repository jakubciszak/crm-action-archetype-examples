<?php

declare(strict_types=1);

namespace Onboarding\Application;

use Onboarding\Domain\OnboardingCase;
use Onboarding\Domain\ScenarioResolver;
use Onboarding\Infrastructure\InMemoryOnboardingCaseRepository;

final class StartOnboardingHandler
{
    public function __construct(
        private readonly ScenarioResolver $scenarioResolver,
        private readonly InMemoryOnboardingCaseRepository $repository,
    ) {}

    public function handle(StartOnboardingCommand $command): OnboardingCase
    {
        $scenario = $this->scenarioResolver->resolve($command->clientType);
        $case = OnboardingCase::fromScenario(
            $command->caseId,
            $command->clientName,
            $command->clientType,
            $scenario,
        );

        $this->repository->save($case);
        return $case;
    }
}
