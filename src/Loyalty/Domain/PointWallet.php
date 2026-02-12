<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final class PointWallet
{
    /** @var WalletEntry[] */
    private array $entries = [];

    /** @var object[] */
    private array $domainEvents = [];

    public function __construct(
        private readonly string $participantId,
    ) {}

    public function participantId(): string
    {
        return $this->participantId;
    }

    /**
     * Add points as PENDING (waiting for deferral period to expire).
     *
     * @param JournalEntry[] $entries
     */
    public function creditPending(string $sourceRef, array $entries): void
    {
        foreach ($entries as $entry) {
            $walletEntry = new WalletEntry(
                points: $entry->points,
                status: WalletEntryStatus::Pending,
                sourceType: 'deferred',
                reason: $entry->reason,
                createdAt: new \DateTimeImmutable(),
                sourceRef: $sourceRef,
                sourceItemRef: $entry->sourceItemRef,
                label: $entry->label,
            );
            $this->entries[] = $walletEntry;
            $this->domainEvents[] = new PointsPending(
                $this->participantId, $sourceRef, $entry->sourceItemRef, $entry->points, $entry->label,
            );
        }
    }

    /**
     * Add points as immediately ACTIVE (e.g. referral bonus).
     */
    public function creditActive(JournalEntry $entry, \DateTimeImmutable $expiresAt): void
    {
        $walletEntry = new WalletEntry(
            points: $entry->points,
            status: WalletEntryStatus::Active,
            sourceType: 'instant',
            reason: $entry->reason,
            createdAt: new \DateTimeImmutable(),
            sourceRef: $entry->sourceRef,
            label: $entry->label,
            expiresAt: $expiresAt,
        );
        $this->entries[] = $walletEntry;
        $this->domainEvents[] = new PointsActivated(
            $this->participantId, $entry->points, $entry->reason, $expiresAt,
        );
    }

    /**
     * Debit pending points for a specific source item (e.g. returned product).
     */
    public function debitItem(string $sourceRef, string $sourceItemRef): int
    {
        foreach ($this->entries as $entry) {
            if ($entry->sourceRef === $sourceRef
                && $entry->sourceItemRef === $sourceItemRef
                && $entry->status() === WalletEntryStatus::Pending
            ) {
                $entry->debit();
                $this->domainEvents[] = new PointsDebitedForReturn(
                    $this->participantId, $sourceRef, $sourceItemRef, $entry->points, $entry->label,
                );
                return $entry->points;
            }
        }
        throw new \DomainException("No pending entry for {$sourceRef}/{$sourceItemRef}");
    }

    /**
     * When deferral period expires: move all pending entries for this source to active.
     */
    public function activateSource(string $sourceRef, \DateTimeImmutable $expiresAt): int
    {
        $activated = 0;
        foreach ($this->entries as $entry) {
            if ($entry->sourceRef === $sourceRef && $entry->status() === WalletEntryStatus::Pending) {
                $entry->activate($expiresAt);
                $activated += $entry->points;
            }
        }
        if ($activated > 0) {
            $this->domainEvents[] = new PointsActivated(
                $this->participantId, $activated, "Source {$sourceRef} deferral period expired", $expiresAt,
            );
        }
        return $activated;
    }

    /**
     * Expire active entries past their expiresAt date.
     */
    public function expireEntries(\DateTimeImmutable $now): int
    {
        $expired = 0;
        foreach ($this->entries as $entry) {
            if ($entry->isExpiredAt($now)) {
                $entry->expire();
                $expired += $entry->points;
                $this->domainEvents[] = new PointsExpired(
                    $this->participantId, $entry->points, $entry->reason,
                );
            }
        }
        return $expired;
    }

    public function pendingBalance(): int
    {
        return $this->sumByStatus(WalletEntryStatus::Pending);
    }

    public function activeBalance(): int
    {
        return $this->sumByStatus(WalletEntryStatus::Active);
    }

    /** @return WalletEntry[] */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return WalletEntry[] */
    public function entriesByStatus(WalletEntryStatus $status): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(WalletEntry $e) => $e->status() === $status,
        ));
    }

    /** @return WalletEntry[] */
    public function entriesBySource(string $sourceRef): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(WalletEntry $e) => $e->sourceRef === $sourceRef,
        ));
    }

    /** @return object[] */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function sumByStatus(WalletEntryStatus $status): int
    {
        $total = 0;
        foreach ($this->entries as $entry) {
            if ($entry->status() === $status) {
                $total += $entry->points;
            }
        }
        return $total;
    }
}
