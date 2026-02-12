<?php

declare(strict_types=1);

namespace CrmArchetype\Lifecycle;

final readonly class LifecycleResult
{
    private function __construct(
        public string $status,
        public ?string $externalRef = null,
        public ?string $message = null,
    ) {}

    public static function completed(string $message = 'OK'): self
    {
        return new self(status: 'completed', message: $message);
    }

    public static function awaitingCallback(string $externalRef): self
    {
        return new self(status: 'awaiting_callback', externalRef: $externalRef);
    }

    public static function failed(string $message): self
    {
        return new self(status: 'failed', message: $message);
    }

    public function isAwaitingCallback(): bool
    {
        return $this->status === 'awaiting_callback';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
