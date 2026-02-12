<?php

declare(strict_types=1);

namespace CrmArchetype\Lifecycle;

use CrmArchetype\Archetype\Action;
use CrmArchetype\Archetype\ActionState;

final class ActionLifecycleDispatcher
{
    /** @var ActionLifecycleHandler[] */
    private readonly array $handlers;

    /**
     * @param iterable<ActionLifecycleHandler> $handlers
     */
    public function __construct(iterable $handlers)
    {
        $this->handlers = $handlers instanceof \Traversable
            ? iterator_to_array($handlers)
            : (array) $handlers;
    }

    public function dispatch(Action $action, string $actionType, ActionState $from, ActionState $to): ActionLifecycleResult
    {
        foreach ($this->handlers as $handler) {
            if (!$handler->supports($actionType, $from, $to)) {
                continue;
            }

            $result = $handler->handle($action, $from, $to);

            if ($result->isAwaitingCallback() || $result->isFailed()) {
                return $result;
            }

            return $result;
        }

        return ActionLifecycleResult::completed();
    }
}
