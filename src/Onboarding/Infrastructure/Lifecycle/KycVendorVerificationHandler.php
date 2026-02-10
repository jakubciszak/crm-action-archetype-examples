<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Infrastructure\Lifecycle;

use CrmArchetype\Archetype\Action;
use CrmArchetype\Archetype\ActionState;
use CrmArchetype\Lifecycle\ActionLifecycleHandler;
use CrmArchetype\Lifecycle\ActionLifecycleResult;
use CrmArchetype\Lifecycle\PendingCallback;
use CrmArchetype\Lifecycle\PendingCallbackRepository;
use CrmArchetype\Onboarding\Infrastructure\Vendor\KycVendorClient;

final readonly class KycVendorVerificationHandler implements ActionLifecycleHandler
{
    public function __construct(
        private KycVendorClient $kycClient,
        private PendingCallbackRepository $pendingCallbacks,
        private string $caseId,
        private string $stageCode,
    ) {}

    public function supports(string $actionType, ActionState $from, ActionState $to): bool
    {
        return $actionType === 'kyc_doc_verification'
            && $from === ActionState::Pending
            && $to === ActionState::InProgress;
    }

    public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult
    {
        $response = $this->kycClient->submitVerification(
            documentIds: [$action->id()],
            callbackUrl: '/api/webhooks/kyc/' . $action->id(),
        );

        $this->pendingCallbacks->store(new PendingCallback(
            actionId: $action->id(),
            caseId: $this->caseId,
            stageCode: $this->stageCode,
            stepCode: $action->id(),
            externalReference: $response->verificationId,
            vendor: 'kyc_provider',
            expectedCallbackBy: new \DateTimeImmutable('+24 hours'),
        ));

        return ActionLifecycleResult::awaitingCallback($response->verificationId);
    }
}
