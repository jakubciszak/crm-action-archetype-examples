<?php

declare(strict_types=1);

namespace App\SharedKernel\CrmArchetype\Model;

/**
 * PartySignature — kto wykonał i/lub zatwierdził Action.
 * Zapewnia audit trail w modelu domenowym.
 */
final class PartySignature
{
    private \DateTimeImmutable $signedAt;

    public function __construct(
        private readonly string $partyId,
        private readonly string $role,
    ) {
        $this->signedAt = new \DateTimeImmutable();
    }

    public function partyId(): string
    {
        return $this->partyId;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function signedAt(): \DateTimeImmutable
    {
        return $this->signedAt;
    }
}
