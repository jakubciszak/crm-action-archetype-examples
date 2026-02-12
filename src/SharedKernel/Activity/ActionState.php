<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

enum ActionState: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case OnHold = 'on_hold';
    case Escalated = 'escalated';

    /** @return ActionState[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending],
            self::Pending => [self::InProgress, self::OnHold],
            self::InProgress => [self::AwaitingApproval, self::Completed, self::Failed, self::Escalated],
            self::AwaitingApproval => [self::Completed, self::Failed],
            self::Failed => [self::InProgress],
            self::OnHold => [self::Pending],
            self::Completed => [],
            self::Escalated => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Escalated => true,
            default => false,
        };
    }
}
