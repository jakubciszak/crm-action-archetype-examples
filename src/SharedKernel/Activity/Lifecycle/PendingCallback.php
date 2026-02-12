<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Lifecycle;

final class PendingCallback
{
    private bool $resolved = false;

    public function __construct(
        public readonly string $actionId,
        public readonly string $externalReference,
        public readonly string $vendor,
        public readonly \DateTimeImmutable $expectedCallbackBy,
    ) {}

    public function markResolved(): void
    {
        $this->resolved = true;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function isOverdue(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $now > $this->expectedCallbackBy;
    }
}
