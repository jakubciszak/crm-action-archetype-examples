<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Lifecycle;

use SharedKernel\Activity\Action;
use SharedKernel\Activity\ActionState;

interface ActionLifecycleHandler
{
    public function supports(string $actionType, ActionState $from, ActionState $to): bool;

    public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult;
}
