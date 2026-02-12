<?php

declare(strict_types=1);

namespace Onboarding\Application;

use Onboarding\Infrastructure\InMemoryOnboardingCaseRepository;
use Onboarding\Infrastructure\InMemoryPendingCallbackRepository;
use SharedKernel\Activity\Outcome;
use SharedKernel\Activity\PartySignature;

final class RecordExternalOutcomeHandler
{
    public function __construct(
        private readonly InMemoryPendingCallbackRepository $pendingCallbacks,
        private readonly InMemoryOnboardingCaseRepository $caseRepository,
    ) {}

    public function handle(RecordExternalOutcomeCommand $command): void
    {
        $callback = $this->pendingCallbacks->findByExternalReference($command->externalReference);
        if ($callback === null) {
            throw new \DomainException("No pending callback for reference '{$command->externalReference}'");
        }

        $case = $this->caseRepository->findById(
            $this->extractCaseId($callback->actionId),
        );
        if ($case === null) {
            throw new \DomainException("OnboardingCase not found for action '{$callback->actionId}'");
        }

        $vendorSignature = new PartySignature(
            partyId: $command->vendor,
            role: 'vendor',
            signedAt: new \DateTimeImmutable(),
        );

        $outcome = new Outcome(
            code: $command->outcomeCode,
            description: $command->outcomeDescription,
            approver: $vendorSignature,
        );

        $stage = $case->currentStage();
        if ($stage !== null) {
            $step = $stage->findStep($this->extractStepCode($callback->actionId));
            if ($step !== null) {
                $case->completeStep(
                    $stage->stageCode(),
                    $step->type(),
                    $outcome,
                    $vendorSignature,
                );
            }
        }

        $callback->markResolved();
        $this->caseRepository->save($case);
    }

    private function extractCaseId(string $actionId): string
    {
        $parts = explode('_', $actionId, 2);
        return $parts[0];
    }

    private function extractStepCode(string $actionId): string
    {
        $parts = explode('_', $actionId, 2);
        return $parts[1] ?? $actionId;
    }
}
