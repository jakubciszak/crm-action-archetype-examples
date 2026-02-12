<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final class WalletEntry
{
    private WalletEntryStatus $status;
    private ?\DateTimeImmutable $expiresAt;

    public function __construct(
        public readonly int $points,
        WalletEntryStatus $status,
        public readonly string $sourceType,
        public readonly string $reason,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?string $orderId = null,
        public readonly ?string $lineId = null,
        public readonly ?string $productName = null,
        ?\DateTimeImmutable $expiresAt = null,
    ) {
        $this->status = $status;
        $this->expiresAt = $expiresAt;
    }

    public function status(): WalletEntryStatus
    {
        return $this->status;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function activate(\DateTimeImmutable $expiresAt): void
    {
        if ($this->status !== WalletEntryStatus::Pending) {
            throw new \DomainException("Cannot activate entry in status {$this->status->value}");
        }
        $this->status = WalletEntryStatus::Active;
        $this->expiresAt = $expiresAt;
    }

    public function debit(): void
    {
        if ($this->status !== WalletEntryStatus::Pending && $this->status !== WalletEntryStatus::Active) {
            throw new \DomainException("Cannot debit entry in status {$this->status->value}");
        }
        $this->status = WalletEntryStatus::Debited;
    }

    public function expire(): void
    {
        if ($this->status !== WalletEntryStatus::Active) {
            throw new \DomainException("Cannot expire entry in status {$this->status->value}");
        }
        $this->status = WalletEntryStatus::Expired;
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return $this->status === WalletEntryStatus::Active
            && $this->expiresAt !== null
            && $now > $this->expiresAt;
    }
}
