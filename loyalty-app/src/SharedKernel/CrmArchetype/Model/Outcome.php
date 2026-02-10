<?php

declare(strict_types=1);

namespace App\SharedKernel\CrmArchetype\Model;

/**
 * Outcome — wynik Action.
 *
 * possibleOutcomes: definiowane deklaratywnie z góry — mapa znanych ścieżek procesu.
 * actualOutcomes:   rejestrowane w runtime — co się naprawdę stało.
 */
final class Outcome
{
    private \DateTimeImmutable $recordedAt;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $code,
        private readonly string $description,
        private readonly array $metadata = [],
        private readonly ?PartySignature $approvedBy = null,
    ) {
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function code(): string
    {
        return $this->code;
    }

    public function description(): string
    {
        return $this->description;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function approvedBy(): ?PartySignature
    {
        return $this->approvedBy;
    }

    public function recordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function matches(string $code): bool
    {
        return $this->code === $code;
    }
}
