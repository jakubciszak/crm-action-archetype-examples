<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\State;

/**
 * Rozbudowana maszyna stanów OnboardingStep.
 *
 * Archetyp daje 3 stany (pending/open/closed) — to za mało.
 * Domena onboardingu rozszerza o: Draft, AwaitingApproval, OnHold, Failed, Escalated.
 *
 * Draft → Pending → InProgress → AwaitingApproval → Completed
 *                        ↕              ↓
 *                     OnHold         Failed
 *                                      ↓
 *                                  Escalated
 */
enum OnboardingStepStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Failed = 'failed';
    case Escalated = 'escalated';

    /**
     * @return self[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending],
            self::Pending => [self::InProgress],
            self::InProgress => [self::AwaitingApproval, self::OnHold, self::Failed],
            self::AwaitingApproval => [self::Completed, self::Failed],
            self::OnHold => [self::InProgress],
            self::Failed => [self::InProgress, self::Escalated],
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
        return $this === self::Completed || $this === self::Escalated;
    }
}
