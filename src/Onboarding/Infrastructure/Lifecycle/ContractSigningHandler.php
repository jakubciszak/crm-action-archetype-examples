<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Infrastructure\Lifecycle;

use CrmArchetype\Archetype\Action;
use CrmArchetype\Archetype\ActionState;
use CrmArchetype\Lifecycle\ActionLifecycleHandler;
use CrmArchetype\Lifecycle\ActionLifecycleResult;
use CrmArchetype\Lifecycle\PendingCallback;
use CrmArchetype\Lifecycle\PendingCallbackRepository;
use CrmArchetype\Onboarding\Infrastructure\Vendor\DocuSignClient;

final readonly class ContractSigningHandler implements ActionLifecycleHandler
{
    public function __construct(
        private DocuSignClient $docuSignClient,
        private PendingCallbackRepository $pendingCallbacks,
        private string $caseId,
        private string $stageCode,
    ) {}

    public function supports(string $actionType, ActionState $from, ActionState $to): bool
    {
        return $actionType === 'contract_signing'
            && $from === ActionState::Pending
            && $to === ActionState::InProgress;
    }

    public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult
    {
        $response = $this->docuSignClient->sendForSignature(
            templateId: 'onboarding-contract-v1',
            signers: [$action->id()],
            callbackUrl: '/api/webhooks/docusign/' . $action->id(),
        );

        $this->pendingCallbacks->store(new PendingCallback(
            actionId: $action->id(),
            caseId: $this->caseId,
            stageCode: $this->stageCode,
            stepCode: $action->id(),
            externalReference: $response->envelopeId,
            vendor: 'docusign',
            expectedCallbackBy: new \DateTimeImmutable('+72 hours'),
        ));

        return ActionLifecycleResult::awaitingCallback($response->envelopeId);
    }
}
