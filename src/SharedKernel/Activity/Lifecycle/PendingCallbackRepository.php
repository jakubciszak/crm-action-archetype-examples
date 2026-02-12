<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Lifecycle;

interface PendingCallbackRepository
{
    public function store(PendingCallback $callback): void;

    public function findByExternalReference(string $externalReference): ?PendingCallback;

    public function findByActionId(string $actionId): ?PendingCallback;
}
