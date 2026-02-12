<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final readonly class IncentiveDecision
{
    /**
     * @param JournalEntry[] $journalEntries
     * @param RewardGrant[] $rewardGrants
     * @param object[] $events
     */
    public function __construct(
        public array $journalEntries,
        public array $rewardGrants,
        public array $events = [],
    ) {}
}
