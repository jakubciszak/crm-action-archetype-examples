<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Lifecycle;

use SharedKernel\Activity\Action;
use SharedKernel\Activity\ActionState;

final class ActionLifecycleDispatcher
{
    /** @param ActionLifecycleHandler[] $handlers */
    public function __construct(private iterable $handlers) {}

    public function dispatch(Action $action, string $actionType, ActionState $from, ActionState $to): ActionLifecycleResult
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($actionType, $from, $to)) {
                return $handler->handle($action, $from, $to);
            }
        }
        return ActionLifecycleResult::completed();
    }
}
