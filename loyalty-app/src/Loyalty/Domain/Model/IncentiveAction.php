<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Model;

use App\Loyalty\Domain\State\IncentiveActionStatus;
use App\SharedKernel\CrmArchetype\Model\Outcome;
use App\SharedKernel\CrmArchetype\Model\PartySignature;

/**
 * IncentiveAction NIE DZIEDZICZY po Action — celowy wybór.
 * Zupełnie inna maszyna stanów: 6 stanów zamiast 8.
 * Ale ten sam wzorzec: state transitions + outcomes + guard conditions.
 *
 * Dziedziczenie gdy maszyna stanów pasuje, kompozycja gdy nie pasuje.
 * Wzorzec jest wspólny.
 *
 * settle() — Outcome emituje efekty biznesowe.
 * reverse() — stan spoza archetypu. Chargeback → cofnięcie punktów.
 */
class IncentiveAction
{
    private IncentiveActionStatus $status;

    /** @var Outcome[] possibleOutcomes — znane z góry */
    private array $possibleOutcomes;

    /** @var Outcome[] actualOutcomes — rejestrowane w runtime */
    private array $actualOutcomes = [];

    /** @var JournalEntry[] */
    private array $journalEntries = [];

    /** @var RewardGrant[] */
    private array $rewardGrants = [];

    private ?PartySignature $performedBy = null;
    private \DateTimeImmutable $createdAt;

    /**
     * @param Outcome[] $possibleOutcomes
     */
    public function __construct(
        private readonly string $id,
        private readonly string $memberId,
        private readonly string $eventType,
        array $possibleOutcomes = [],
    ) {
        $this->status = IncentiveActionStatus::Received;
        $this->possibleOutcomes = $possibleOutcomes ?: IncentiveDecision::standardPossibleOutcomes();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function status(): IncentiveActionStatus
    {
        return $this->status;
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

    /** @return JournalEntry[] */
    public function journalEntries(): array
    {
        return $this->journalEntries;
    }

    /** @return RewardGrant[] */
    public function rewardGrants(): array
    {
        return $this->rewardGrants;
    }

    /**
     * Received → Evaluating: rozpocznij ewaluację.
     */
    public function evaluate(PartySignature $evaluator): void
    {
        $this->transitionTo(IncentiveActionStatus::Evaluating);
        $this->performedBy = $evaluator;
    }

    /**
     * Evaluating → Rejected: odrzuć incentive.
     */
    public function reject(Outcome $outcome): void
    {
        $this->transitionTo(IncentiveActionStatus::Rejected);
        $this->actualOutcomes[] = $outcome;
    }

    /**
     * Evaluating → AwaitingSettlement: zatwierdź do rozliczenia.
     */
    public function approveForSettlement(Outcome $outcome): void
    {
        $this->transitionTo(IncentiveActionStatus::AwaitingSettlement);
        $this->actualOutcomes[] = $outcome;
    }

    /**
     * AwaitingSettlement → Settled: rozlicz incentive.
     * Outcome emituje efekty biznesowe: journalEntries, rewardGrants.
     */
    public function settle(Outcome $outcome): void
    {
        $this->transitionTo(IncentiveActionStatus::Settled);
        $this->actualOutcomes[] = $outcome;

        // Emituj efekty biznesowe na podstawie outcome
        $this->emitBusinessEffects($outcome);
    }

    /**
     * Settled → Reversed: chargeback — cofnięcie punktów.
     * Stan spoza archetypu. Głęboki model rozszerza archetyp o potrzeby domeny.
     */
    public function reverse(string $reason): void
    {
        $this->transitionTo(IncentiveActionStatus::Reversed);

        // Cofnij wszystkie journal entries
        foreach ($this->journalEntries as $entry) {
            if ($entry->isCredit()) {
                $this->journalEntries[] = new JournalEntry(
                    $entry->memberId(),
                    -$entry->points(),
                    'Cofnięcie: ' . $reason,
                );
            }
        }

        $this->actualOutcomes[] = new Outcome('reversed', 'Cofnięto', ['reason' => $reason]);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function hasOutcome(string $code): bool
    {
        foreach ($this->actualOutcomes as $outcome) {
            if ($outcome->matches($code)) {
                return true;
            }
        }

        return false;
    }

    public function totalPointsGranted(): int
    {
        return array_sum(
            array_map(
                fn (JournalEntry $e) => $e->points(),
                $this->journalEntries,
            ),
        );
    }

    private function emitBusinessEffects(Outcome $outcome): void
    {
        $metadata = $outcome->metadata();

        if ($outcome->matches(IncentiveDecision::POINTS_GRANTED) && isset($metadata['points'])) {
            $this->journalEntries[] = new JournalEntry(
                $this->memberId,
                (int) $metadata['points'],
                $outcome->description(),
            );
        }

        if ($outcome->matches(IncentiveDecision::REWARD_GRANT) && isset($metadata['reward_code'])) {
            $this->rewardGrants[] = new RewardGrant(
                $this->memberId,
                (string) $metadata['reward_code'],
                $outcome->description(),
            );
        }
    }

    private function transitionTo(IncentiveActionStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(sprintf(
                'Niedozwolone przejście stanu: %s → %s',
                $this->status->value,
                $target->value,
            ));
        }

        $this->status = $target;
    }
}
