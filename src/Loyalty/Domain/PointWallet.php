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
     * Add points from order lines as PENDING (waiting for return period to expire).
     *
     * @param JournalEntry[] $lineEntries
     */
    public function creditPendingFromOrder(string $orderId, array $lineEntries): void
    {
        foreach ($lineEntries as $entry) {
            $walletEntry = new WalletEntry(
                points: $entry->points,
                status: WalletEntryStatus::Pending,
                sourceType: 'order',
                reason: $entry->reason,
                createdAt: new \DateTimeImmutable(),
                orderId: $orderId,
                lineId: $entry->lineId,
                productName: $entry->productName,
            );
            $this->entries[] = $walletEntry;
            $this->domainEvents[] = new PointsPending(
                $this->participantId, $orderId, $entry->lineId, $entry->points, $entry->productName,
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
            sourceType: 'activity',
            reason: $entry->reason,
            createdAt: new \DateTimeImmutable(),
            expiresAt: $expiresAt,
        );
        $this->entries[] = $walletEntry;
        $this->domainEvents[] = new PointsActivated(
            $this->participantId, $entry->points, $entry->reason, $expiresAt,
        );
    }

    /**
     * Debit pending points for a returned order line.
     */
    public function debitForReturn(string $orderId, string $lineId): int
    {
        foreach ($this->entries as $entry) {
            if ($entry->orderId === $orderId
                && $entry->lineId === $lineId
                && $entry->status() === WalletEntryStatus::Pending
            ) {
                $entry->debit();
                $this->domainEvents[] = new PointsDebitedForReturn(
                    $this->participantId, $orderId, $lineId, $entry->points, $entry->productName,
                );
                return $entry->points;
            }
        }
        throw new \DomainException("No pending entry for {$orderId}/{$lineId}");
    }

    /**
     * When return period expires: move all pending entries for this order to active.
     */
    public function activateOrder(string $orderId, \DateTimeImmutable $expiresAt): int
    {
        $activated = 0;
        foreach ($this->entries as $entry) {
            if ($entry->orderId === $orderId && $entry->status() === WalletEntryStatus::Pending) {
                $entry->activate($expiresAt);
                $activated += $entry->points;
            }
        }
        if ($activated > 0) {
            $this->domainEvents[] = new PointsActivated(
                $this->participantId, $activated, "Order {$orderId} return period expired", $expiresAt,
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
    public function entriesByOrder(string $orderId): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(WalletEntry $e) => $e->orderId === $orderId,
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
