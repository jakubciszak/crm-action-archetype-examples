<?php

declare(strict_types=1);

namespace CrmArchetype\Loyalty;

final readonly class IncentiveDecision
{
    /**
     * @param JournalEntry[] $journalEntries
     * @param RewardGrant[]  $rewardGrants
     * @param object[]       $domainEvents
     */
    public function __construct(
        public string $description,
        public array $journalEntries = [],
        public array $rewardGrants = [],
        public array $domainEvents = [],
    ) {}
}
