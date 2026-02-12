<?php

declare(strict_types=1);

namespace SharedKernel\Activity\Lifecycle;

enum ActionLifecycleStatus
{
    case Completed;
    case AwaitingCallback;
    case Failed;
}
