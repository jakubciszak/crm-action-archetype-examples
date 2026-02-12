<?php

declare(strict_types=1);

namespace CrmArchetype\Lifecycle;

interface PendingCallbackRepository
{
    public function store(PendingCallback $callback): void;

    public function findByActionId(string $actionId): ?PendingCallback;

    /** @return PendingCallback[] */
    public function findOverdue(): array;

    public function markResolved(string $actionId): void;
}
