<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Lifecycle;

final readonly class ActionLifecycleResult
{
    private function __construct(
        public ActionLifecycleStatus $status,
        public ?string $externalReference = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}

    public static function completed(array $metadata = []): self
    {
        return new self(ActionLifecycleStatus::Completed, metadata: $metadata);
    }

    public static function awaitingCallback(string $externalReference): self
    {
        return new self(ActionLifecycleStatus::AwaitingCallback, externalReference: $externalReference);
    }

    public static function failed(string $message): self
    {
        return new self(ActionLifecycleStatus::Failed, message: $message);
    }
}
