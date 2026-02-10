<?php

declare(strict_types=1);

namespace CrmArchetype\Lifecycle;

use CrmArchetype\Archetype\Action;
use CrmArchetype\Archetype\ActionState;

interface ActionLifecycleHandler
{
    public function supports(string $actionType, ActionState $from, ActionState $to): bool;

    public function handle(Action $action): LifecycleResult;
}
