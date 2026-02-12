<?php

declare(strict_types=1);

namespace Loyalty\Application;

use Loyalty\Domain\IncentiveAction;
use Loyalty\Infrastructure\InMemoryLoyaltyCampaignRepository;

final class ReverseActionHandler
{
    public function __construct(
        private readonly InMemoryLoyaltyCampaignRepository $repository,
    ) {}

    public function handle(ReverseActionCommand $command): IncentiveAction
    {
        $campaign = $this->repository->findById($command->campaignId);
        if ($campaign === null) {
            throw new \DomainException("Campaign '{$command->campaignId}' not found");
        }

        $action = null;
        foreach ($campaign->streams() as $stream) {
            foreach ($stream->actions() as $a) {
                if ($a->id() === $command->actionId) {
                    $action = $a;
                    break 2;
                }
            }
        }

        if ($action === null) {
            throw new \DomainException("Action '{$command->actionId}' not found in campaign '{$command->campaignId}'");
        }

        $action->reverse($command->reason);
        $this->repository->save($campaign);
        return $action;
    }
}
