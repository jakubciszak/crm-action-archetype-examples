<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Model;

/**
 * Zapis księgowy punktów lojalnościowych.
 */
final class JournalEntry
{
    public function __construct(
        private readonly string $memberId,
        private readonly int $points,
        private readonly string $description,
        private readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function points(): int
    {
        return $this->points;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCredit(): bool
    {
        return $this->points > 0;
    }

    public function isDebit(): bool
    {
        return $this->points < 0;
    }
}
