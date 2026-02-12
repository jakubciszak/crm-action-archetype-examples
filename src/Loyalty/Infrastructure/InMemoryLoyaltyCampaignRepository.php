<?php

declare(strict_types=1);

namespace Loyalty\Infrastructure;

use Loyalty\Domain\LoyaltyCampaign;

final class InMemoryLoyaltyCampaignRepository
{
    /** @var array<string, LoyaltyCampaign> */
    private array $campaigns = [];

    public function save(LoyaltyCampaign $campaign): void
    {
        $this->campaigns[$campaign->id()] = $campaign;
    }

    public function findById(string $id): ?LoyaltyCampaign
    {
        return $this->campaigns[$id] ?? null;
    }
}
