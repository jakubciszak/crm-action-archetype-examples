<?php

declare(strict_types=1);

namespace CrmArchetype\Lifecycle;

final class PendingCallback
{
    private bool $resolved = false;

    public function __construct(
        private readonly string $actionId,
        private readonly string $caseId,
        private readonly string $stageCode,
        private readonly string $stepCode,
        private readonly string $externalReference,
        private readonly string $vendor,
        private readonly \DateTimeImmutable $expectedCallbackBy,
        private readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {}

    public function markResolved(): void
    {
        $this->resolved = true;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function isOverdue(): bool
    {
        return !$this->resolved && new \DateTimeImmutable() > $this->expectedCallbackBy;
    }

    public function actionId(): string
    {
        return $this->actionId;
    }

    public function caseId(): string
    {
        return $this->caseId;
    }

    public function stageCode(): string
    {
        return $this->stageCode;
    }

    public function stepCode(): string
    {
        return $this->stepCode;
    }

    public function externalReference(): string
    {
        return $this->externalReference;
    }

    public function vendor(): string
    {
        return $this->vendor;
    }

    public function expectedCallbackBy(): \DateTimeImmutable
    {
        return $this->expectedCallbackBy;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
