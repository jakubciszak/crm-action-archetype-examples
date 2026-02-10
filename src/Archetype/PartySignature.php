<?php

declare(strict_types=1);

namespace CrmArchetype\Archetype;

final readonly class PartySignature
{
    public function __construct(
        public string $partyId,
        public string $role,
        public ?\DateTimeImmutable $signedAt = null,
    ) {}
}
