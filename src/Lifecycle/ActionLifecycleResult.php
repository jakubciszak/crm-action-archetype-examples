<?php

declare(strict_types=1);

namespace CrmArchetype\Lifecycle;

final readonly class ActionLifecycleResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        public ActionLifecycleStatus $status,
        public ?string $externalReference = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public static function completed(string $message = 'OK', array $metadata = []): self
    {
        return new self(
            status: ActionLifecycleStatus::Completed,
            message: $message,
            metadata: $metadata,
        );
    }

    public static function awaitingCallback(string $externalReference): self
    {
        return new self(
            status: ActionLifecycleStatus::AwaitingCallback,
            externalReference: $externalReference,
        );
    }

    public static function failed(string $message): self
    {
        return new self(
            status: ActionLifecycleStatus::Failed,
            message: $message,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === ActionLifecycleStatus::Completed;
    }

    public function isAwaitingCallback(): bool
    {
        return $this->status === ActionLifecycleStatus::AwaitingCallback;
    }

    public function isFailed(): bool
    {
        return $this->status === ActionLifecycleStatus::Failed;
    }
}
