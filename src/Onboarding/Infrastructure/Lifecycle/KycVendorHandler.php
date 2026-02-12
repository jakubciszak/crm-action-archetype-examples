<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\Lifecycle;

use Onboarding\Infrastructure\Vendor\KycVendorClient;
use SharedKernel\Activity\Action;
use SharedKernel\Activity\ActionState;
use SharedKernel\Activity\Lifecycle\ActionLifecycleHandler;
use SharedKernel\Activity\Lifecycle\ActionLifecycleResult;
use SharedKernel\Activity\Lifecycle\PendingCallback;
use SharedKernel\Activity\Lifecycle\PendingCallbackRepository;

final readonly class KycVendorHandler implements ActionLifecycleHandler
{
    public function __construct(
        private KycVendorClient $client,
        private PendingCallbackRepository $pendingCallbacks,
    ) {}

    public function supports(string $actionType, ActionState $from, ActionState $to): bool
    {
        return $actionType === 'kyc_doc_verification'
            && $from === ActionState::Pending
            && $to === ActionState::InProgress;
    }

    public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult
    {
        $verificationId = $this->client->submitVerification($action->id());

        $this->pendingCallbacks->store(new PendingCallback(
            actionId: $action->id(),
            externalReference: $verificationId,
            vendor: 'kyc_vendor',
            expectedCallbackBy: new \DateTimeImmutable('+24 hours'),
        ));

        return ActionLifecycleResult::awaitingCallback($verificationId);
    }
}
