<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Archetype;

use CrmArchetype\Archetype\ActionState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActionStateTest extends TestCase
{
    #[Test]
    public function draft_can_transition_to_pending(): void
    {
        self::assertTrue(ActionState::Draft->canTransitionTo(ActionState::Pending));
    }

    #[Test]
    public function pending_can_transition_to_in_progress(): void
    {
        self::assertTrue(ActionState::Pending->canTransitionTo(ActionState::InProgress));
    }

    #[Test]
    #[DataProvider('inProgressTransitionsProvider')]
    public function in_progress_allows_multiple_transitions(ActionState $target): void
    {
        self::assertTrue(ActionState::InProgress->canTransitionTo($target));
    }

    public static function inProgressTransitionsProvider(): iterable
    {
        yield 'to AwaitingApproval' => [ActionState::AwaitingApproval];
        yield 'to Completed' => [ActionState::Completed];
        yield 'to OnHold' => [ActionState::OnHold];
        yield 'to Failed' => [ActionState::Failed];
        yield 'to Escalated' => [ActionState::Escalated];
    }

    #[Test]
    public function awaiting_approval_can_transition_to_completed_or_back(): void
    {
        self::assertTrue(ActionState::AwaitingApproval->canTransitionTo(ActionState::Completed));
        self::assertTrue(ActionState::AwaitingApproval->canTransitionTo(ActionState::InProgress));
    }

    #[Test]
    public function on_hold_can_resume_or_escalate(): void
    {
        self::assertTrue(ActionState::OnHold->canTransitionTo(ActionState::InProgress));
        self::assertTrue(ActionState::OnHold->canTransitionTo(ActionState::Escalated));
    }

    #[Test]
    public function failed_can_retry(): void
    {
        self::assertTrue(ActionState::Failed->canTransitionTo(ActionState::InProgress));
    }

    #[Test]
    public function completed_is_terminal(): void
    {
        self::assertTrue(ActionState::Completed->isTerminal());
        self::assertEmpty(ActionState::Completed->allowedTransitions());
    }

    #[Test]
    public function escalated_is_terminal(): void
    {
        self::assertTrue(ActionState::Escalated->isTerminal());
        self::assertEmpty(ActionState::Escalated->allowedTransitions());
    }

    #[Test]
    public function cannot_go_backward_from_completed(): void
    {
        self::assertFalse(ActionState::Completed->canTransitionTo(ActionState::InProgress));
        self::assertFalse(ActionState::Completed->canTransitionTo(ActionState::Draft));
    }

    #[Test]
    public function cannot_skip_states(): void
    {
        self::assertFalse(ActionState::Draft->canTransitionTo(ActionState::InProgress));
        self::assertFalse(ActionState::Draft->canTransitionTo(ActionState::Completed));
        self::assertFalse(ActionState::Pending->canTransitionTo(ActionState::Completed));
    }
}
