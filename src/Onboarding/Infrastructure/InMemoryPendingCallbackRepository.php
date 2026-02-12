<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure;

use SharedKernel\Activity\Lifecycle\PendingCallback;
use SharedKernel\Activity\Lifecycle\PendingCallbackRepository;

final class InMemoryPendingCallbackRepository implements PendingCallbackRepository
{
    /** @var PendingCallback[] */
    private array $callbacks = [];

    public function store(PendingCallback $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function findByExternalReference(string $externalReference): ?PendingCallback
    {
        foreach ($this->callbacks as $callback) {
            if ($callback->externalReference === $externalReference && !$callback->isResolved()) {
                return $callback;
            }
        }
        return null;
    }

    public function findByActionId(string $actionId): ?PendingCallback
    {
        foreach ($this->callbacks as $callback) {
            if ($callback->actionId === $actionId && !$callback->isResolved()) {
                return $callback;
            }
        }
        return null;
    }
}
