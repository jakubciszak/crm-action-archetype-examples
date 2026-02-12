<?php

declare(strict_types=1);

namespace Loyalty\Application;

use Loyalty\Domain\IncentiveAction;
use Loyalty\Domain\IncentiveRule;
use Loyalty\Infrastructure\InMemoryLoyaltyCampaignRepository;

final class RecordActionHandler
{
    /** @param IncentiveRule[] $rules */
    public function __construct(
        private readonly InMemoryLoyaltyCampaignRepository $repository,
        private readonly array $rules,
    ) {}

    public function handle(RecordActionCommand $command): IncentiveAction
    {
        $campaign = $this->repository->findById($command->campaignId);
        if ($campaign === null) {
            throw new \DomainException("Campaign '{$command->campaignId}' not found");
        }

        $action = new IncentiveAction(
            id: $command->actionId,
            actionType: $command->actionType,
            payload: $command->payload,
            participantId: $command->participantId,
            occurredAt: new \DateTimeImmutable(),
        );

        $action->evaluate(...$this->rules);
        $campaign->recordAction($action);

        if ($action->state() === \Loyalty\Domain\IncentiveActionState::AwaitingSettlement) {
            $action->settle();
        }

        $this->repository->save($campaign);
        return $action;
    }
}
