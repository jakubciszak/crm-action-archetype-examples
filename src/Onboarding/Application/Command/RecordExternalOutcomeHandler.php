<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Application\Command;

use CrmArchetype\Archetype\Outcome;
use CrmArchetype\Archetype\PartySignature;
use CrmArchetype\Lifecycle\PendingCallbackRepository;
use CrmArchetype\Onboarding\OnboardingCaseRepository;
use CrmArchetype\Onboarding\OnboardingPhase;
use CrmArchetype\Onboarding\OnboardingStep;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordExternalOutcomeHandler
{
    public function __construct(
        private PendingCallbackRepository $pendingCallbacks,
        private OnboardingCaseRepository $caseRepository,
    ) {}

    public function __invoke(RecordExternalOutcomeCommand $command): void
    {
        $callback = $this->pendingCallbacks->findByActionId($command->actionId);

        if ($callback === null) {
            throw new \RuntimeException(sprintf('No pending callback for action "%s".', $command->actionId));
        }

        $case = $this->caseRepository->findById($callback->caseId());

        if ($case === null) {
            throw new \RuntimeException(sprintf('Onboarding case "%s" not found.', $callback->caseId()));
        }

        $step = $this->findStep($case->phases(), $callback->stageCode(), $callback->stepCode());

        if ($step === null) {
            throw new \RuntimeException(sprintf(
                'Step "%s" not found in stage "%s".',
                $callback->stepCode(),
                $callback->stageCode(),
            ));
        }

        $outcome = new Outcome(
            description: $command->outcomeDescription,
            reason: $command->outcomeReason,
            approvers: [
                new PartySignature(
                    partyId: 'vendor:' . $callback->vendor(),
                    role: 'external_verifier',
                ),
            ],
        );

        match ($command->outcomeDescription) {
            'Zaakceptowane', 'Podpisano', 'Gotowe', 'Ukonczone' => $step->complete($outcome),
            'DoUzupelnienia' => $step->recordOutcome($outcome),
            'Odrzucone', 'Odrzucony' => $this->failStep($step, $outcome, $command->outcomeReason),
            default => $step->recordOutcome($outcome),
        };

        $this->pendingCallbacks->markResolved($command->actionId);
    }

    /**
     * @param OnboardingPhase[] $phases
     */
    private function findStep(array $phases, string $stageCode, string $stepCode): ?OnboardingStep
    {
        foreach ($phases as $phase) {
            if ($phase->topicName() !== $stageCode) {
                continue;
            }

            foreach ($phase->steps() as $step) {
                if ($step->id() === $stepCode) {
                    return $step;
                }
            }
        }

        return null;
    }

    private function failStep(OnboardingStep $step, Outcome $outcome, ?string $reason): void
    {
        $step->recordOutcome($outcome);
        $step->fail($reason ?? 'Rejected by external vendor');
    }
}
