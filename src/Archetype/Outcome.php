<?php

declare(strict_types=1);

namespace CrmArchetype\Archetype;

final readonly class Outcome
{
    /**
     * @param PartySignature[] $approvers
     */
    public function __construct(
        public string $description,
        public ?string $reason = null,
        public array $approvers = [],
    ) {}
}
