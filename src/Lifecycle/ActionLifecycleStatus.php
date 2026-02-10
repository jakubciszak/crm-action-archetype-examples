<?php

declare(strict_types=1);

namespace CrmArchetype\Lifecycle;

enum ActionLifecycleStatus: string
{
    case Completed = 'completed';
    case AwaitingCallback = 'awaiting_callback';
    case Failed = 'failed';
}
