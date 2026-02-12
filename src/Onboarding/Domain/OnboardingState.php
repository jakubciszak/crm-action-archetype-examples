<?php

declare(strict_types=1);

namespace Onboarding\Domain;

enum OnboardingState: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Failed = 'failed';
    case Escalated = 'escalated';
}
