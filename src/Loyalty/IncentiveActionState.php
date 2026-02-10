<?php

declare(strict_types=1);

namespace CrmArchetype\Loyalty;

enum IncentiveActionState: string
{
    case Received = 'received';
    case Evaluating = 'evaluating';
    case AwaitingSettlement = 'awaiting_settlement';
    case Settled = 'settled';
    case Rejected = 'rejected';
    case Reversed = 'reversed';

    /**
     * @return IncentiveActionState[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::Evaluating],
            self::Evaluating => [self::AwaitingSettlement, self::Rejected],
            self::AwaitingSettlement => [self::Settled],
            self::Settled => [self::Reversed],
            self::Rejected, self::Reversed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Settled, self::Rejected, self::Reversed => true,
            default => false,
        };
    }
}
