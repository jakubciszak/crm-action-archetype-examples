<?php

declare(strict_types=1);

namespace App\SharedKernel\CrmArchetype\Model;

use App\SharedKernel\CrmArchetype\State\ActionStatus;

/**
 * Action — krok procesu z archetypu CRM Activity.
 *
 * Triada Action + Outcome + Status opisuje KAŻDY proces,
 * w którym ktoś robi coś z jakimś wynikiem.
 *
 * possibleOutcomes — definiowane z góry, tworzą mapę znanych ścieżek.
 * actualOutcomes   — rejestrowane w runtime, każda Action MUSI mieć co najmniej jeden.
 */
abstract class Action
{
    protected string $id;
    protected ActionStatus $status;
    /** @var Outcome[] */
    protected array $possibleOutcomes = [];
    /** @var Outcome[] */
    protected array $actualOutcomes = [];
    protected ?PartySignature $performedBy = null;
    protected ?PartySignature $approvedBy = null;
    protected \DateTimeImmutable $createdAt;
    protected ?\DateTimeImmutable $updatedAt = null;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->status = ActionStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function status(): ActionStatus
    {
        return $this->status;
    }

    public function assignPerformer(PartySignature $performer): void
    {
        $this->performedBy = $performer;
    }

    public function performedBy(): ?PartySignature
    {
        return $this->performedBy;
    }

    public function approvedBy(): ?PartySignature
    {
        return $this->approvedBy;
    }

    /**
     * Definiuje possibleOutcomes — znane z góry ścieżki procesu.
     * @param Outcome[] $outcomes
     */
    protected function definePossibleOutcomes(array $outcomes): void
    {
        $this->possibleOutcomes = $outcomes;
    }

    /** @return Outcome[] */
    public function possibleOutcomes(): array
    {
        return $this->possibleOutcomes;
    }

    /** @return Outcome[] */
    public function actualOutcomes(): array
    {
        return $this->actualOutcomes;
    }

    protected function recordOutcome(Outcome $outcome): void
    {
        $this->actualOutcomes[] = $outcome;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function hasActualOutcome(string $code): bool
    {
        foreach ($this->actualOutcomes as $outcome) {
            if ($outcome->matches($code)) {
                return true;
            }
        }

        return false;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
