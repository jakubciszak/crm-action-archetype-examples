<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\State;

/**
 * Maszyna stanów IncentiveAction — zupełnie inna niż Onboarding.
 * 6 stanów zamiast 8. Event-driven, nie sekwencyjny.
 *
 * Received → Evaluating → AwaitingSettlement → Settled
 *                ↓                                ↓
 *             Rejected                         Reversed
 *
 * Reversed — stan spoza archetypu. Chargeback → cofnięcie punktów.
 * Głęboki model rozszerza archetyp o potrzeby domeny.
 */
enum IncentiveActionStatus: string
{
    case Received = 'received';
    case Evaluating = 'evaluating';
    case AwaitingSettlement = 'awaiting_settlement';
    case Settled = 'settled';
    case Rejected = 'rejected';
    case Reversed = 'reversed';

    /**
     * @return self[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::Evaluating],
            self::Evaluating => [self::AwaitingSettlement, self::Rejected],
            self::AwaitingSettlement => [self::Settled],
            self::Settled => [self::Reversed],
            self::Rejected => [],
            self::Reversed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Settled, self::Rejected, self::Reversed], true);
    }
}
