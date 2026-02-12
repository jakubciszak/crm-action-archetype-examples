<?php

declare(strict_types=1);

namespace Onboarding\Application;

use Onboarding\Infrastructure\InMemoryOnboardingCaseRepository;
use SharedKernel\Activity\Outcome;
use SharedKernel\Activity\OutcomeDirective;

final class CompleteStepHandler
{
    public function __construct(
        private readonly InMemoryOnboardingCaseRepository $repository,
    ) {}

    /** @return OutcomeDirective[] */
    public function handle(CompleteStepCommand $command): array
    {
        $case = $this->repository->findById($command->caseId);
        if ($case === null) {
            throw new \DomainException("OnboardingCase '{$command->caseId}' not found");
        }

        $outcome = new Outcome(
            code: $command->outcomeCode,
            description: $command->outcomeDescription,
        );

        $directives = $case->completeStep(
            $command->stageCode,
            $command->stepCode,
            $outcome,
            $command->approver,
        );

        $this->repository->save($case);
        return $directives;
    }
}
