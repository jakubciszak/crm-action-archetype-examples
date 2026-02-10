<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Loyalty;

use CrmArchetype\Loyalty\IncentiveActionState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IncentiveActionStateTest extends TestCase
{
    #[Test]
    public function received_can_only_go_to_evaluating(): void
    {
        $state = IncentiveActionState::Received;

        self::assertTrue($state->canTransitionTo(IncentiveActionState::Evaluating));
        self::assertFalse($state->canTransitionTo(IncentiveActionState::Settled));
        self::assertFalse($state->canTransitionTo(IncentiveActionState::Rejected));
    }

    #[Test]
    public function evaluating_can_go_to_awaiting_settlement_or_rejected(): void
    {
        $state = IncentiveActionState::Evaluating;

        self::assertTrue($state->canTransitionTo(IncentiveActionState::AwaitingSettlement));
        self::assertTrue($state->canTransitionTo(IncentiveActionState::Rejected));
        self::assertFalse($state->canTransitionTo(IncentiveActionState::Settled));
    }

    #[Test]
    public function awaiting_settlement_can_go_to_settled(): void
    {
        $state = IncentiveActionState::AwaitingSettlement;

        self::assertTrue($state->canTransitionTo(IncentiveActionState::Settled));
        self::assertFalse($state->canTransitionTo(IncentiveActionState::Rejected));
    }

    #[Test]
    public function settled_can_be_reversed(): void
    {
        self::assertTrue(IncentiveActionState::Settled->canTransitionTo(IncentiveActionState::Reversed));
    }

    #[Test]
    public function terminal_states(): void
    {
        self::assertTrue(IncentiveActionState::Settled->isTerminal());
        self::assertTrue(IncentiveActionState::Rejected->isTerminal());
        self::assertTrue(IncentiveActionState::Reversed->isTerminal());

        self::assertFalse(IncentiveActionState::Received->isTerminal());
        self::assertFalse(IncentiveActionState::Evaluating->isTerminal());
    }

    #[Test]
    public function rejected_and_reversed_are_dead_ends(): void
    {
        self::assertEmpty(IncentiveActionState::Rejected->allowedTransitions());
        self::assertEmpty(IncentiveActionState::Reversed->allowedTransitions());
    }
}
