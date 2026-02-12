<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Infrastructure\Lifecycle;

use CrmArchetype\Archetype\Action;
use CrmArchetype\Archetype\ActionState;
use CrmArchetype\Lifecycle\ActionLifecycleHandler;
use CrmArchetype\Lifecycle\ActionLifecycleResult;
use CrmArchetype\Lifecycle\PendingCallback;
use CrmArchetype\Lifecycle\PendingCallbackRepository;
use CrmArchetype\Onboarding\Infrastructure\Vendor\InfrastructureApi;
use CrmArchetype\Onboarding\Infrastructure\Vendor\ProvisioningQueuedException;

final readonly class EnvProvisioningHandler implements ActionLifecycleHandler
{
    public function __construct(
        private InfrastructureApi $infraApi,
        private PendingCallbackRepository $pendingCallbacks,
        private string $caseId,
        private string $stageCode,
    ) {}

    public function supports(string $actionType, ActionState $from, ActionState $to): bool
    {
        return $actionType === 'env_provisioning'
            && $from === ActionState::Pending
            && $to === ActionState::InProgress;
    }

    public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult
    {
        try {
            $result = $this->infraApi->provisionTenant(
                clientId: $action->id(),
                tier: 'standard',
            );

            return ActionLifecycleResult::completed(
                message: 'Tenant provisioned',
                metadata: ['tenantUrl' => $result->tenantUrl],
            );
        } catch (ProvisioningQueuedException $e) {
            $this->pendingCallbacks->store(new PendingCallback(
                actionId: $action->id(),
                caseId: $this->caseId,
                stageCode: $this->stageCode,
                stepCode: $action->id(),
                externalReference: $e->jobId,
                vendor: 'infrastructure',
                expectedCallbackBy: new \DateTimeImmutable('+1 hour'),
            ));

            return ActionLifecycleResult::awaitingCallback($e->jobId);
        }
    }
}
