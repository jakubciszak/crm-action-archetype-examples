<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Archetype;

use CrmArchetype\Archetype\Action;
use CrmArchetype\Archetype\ActionState;
use CrmArchetype\Archetype\InvalidStateTransitionException;
use CrmArchetype\Archetype\Outcome;
use CrmArchetype\Archetype\PartySignature;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActionTest extends TestCase
{
    #[Test]
    public function starts_in_draft_state(): void
    {
        $action = $this->createAction();

        self::assertSame(ActionState::Draft, $action->state());
    }

    #[Test]
    public function happy_path_draft_to_completed(): void
    {
        $action = $this->createAction();
        $outcome = new Outcome('Success');

        $action->submit();
        self::assertSame(ActionState::Pending, $action->state());

        $action->start();
        self::assertSame(ActionState::InProgress, $action->state());
        self::assertNotNull($action->startedAt());

        $action->complete($outcome);
        self::assertSame(ActionState::Completed, $action->state());
        self::assertNotNull($action->endedAt());
        self::assertCount(1, $action->actualOutcomes());
        self::assertSame('Success', $action->actualOutcomes()[0]->description);
    }

    #[Test]
    public function happy_path_with_approval(): void
    {
        $action = $this->createAction();
        $outcome = new Outcome('Approved');

        $action->submit();
        $action->start();
        $action->requestApproval();
        self::assertSame(ActionState::AwaitingApproval, $action->state());

        $action->approve($outcome);
        self::assertSame(ActionState::Completed, $action->state());
    }

    #[Test]
    public function approval_rejection_sends_back_to_in_progress(): void
    {
        $action = $this->createAction();

        $action->submit();
        $action->start();
        $action->requestApproval();
        $action->rejectApproval();

        self::assertSame(ActionState::InProgress, $action->state());
    }

    #[Test]
    public function hold_and_resume(): void
    {
        $action = $this->createAction();

        $action->submit();
        $action->start();
        $action->hold();
        self::assertSame(ActionState::OnHold, $action->state());

        $action->resume();
        self::assertSame(ActionState::InProgress, $action->state());
    }

    #[Test]
    public function fail_and_retry(): void
    {
        $action = $this->createAction();

        $action->submit();
        $action->start();
        $action->fail('Network error');
        self::assertSame(ActionState::Failed, $action->state());
        self::assertSame('Network error', $action->reason());

        $action->retry();
        self::assertSame(ActionState::InProgress, $action->state());
    }

    #[Test]
    public function escalation_from_in_progress(): void
    {
        $action = $this->createAction();

        $action->submit();
        $action->start();
        $action->escalate();

        self::assertSame(ActionState::Escalated, $action->state());
    }

    #[Test]
    public function escalation_from_on_hold(): void
    {
        $action = $this->createAction();

        $action->submit();
        $action->start();
        $action->hold();
        $action->escalate();

        self::assertSame(ActionState::Escalated, $action->state());
    }

    #[Test]
    public function cannot_start_from_draft(): void
    {
        $action = $this->createAction();

        $this->expectException(InvalidStateTransitionException::class);
        $action->start();
    }

    #[Test]
    public function cannot_complete_from_draft(): void
    {
        $action = $this->createAction();

        $this->expectException(InvalidStateTransitionException::class);
        $action->complete(new Outcome('test'));
    }

    #[Test]
    public function cannot_resume_from_pending(): void
    {
        $action = $this->createAction();
        $action->submit();

        $this->expectException(InvalidStateTransitionException::class);
        $action->resume();
    }

    #[Test]
    public function cannot_transition_after_completed(): void
    {
        $action = $this->createAction();
        $action->submit();
        $action->start();
        $action->complete(new Outcome('Done'));

        $this->expectException(InvalidStateTransitionException::class);
        $action->start();
    }

    #[Test]
    public function possible_outcomes_are_set_at_construction(): void
    {
        $outcomes = [new Outcome('OK'), new Outcome('Rejected')];
        $action = new Action('a1', 'Test', possibleOutcomes: $outcomes);

        self::assertCount(2, $action->possibleOutcomes());
        self::assertSame('OK', $action->possibleOutcomes()[0]->description);
    }

    #[Test]
    public function records_multiple_outcomes(): void
    {
        $action = $this->createAction();

        $action->recordOutcome(new Outcome('Partial'));
        $action->recordOutcome(new Outcome('Final'));

        self::assertCount(2, $action->actualOutcomes());
    }

    #[Test]
    public function initiator_and_approvers(): void
    {
        $initiator = new PartySignature('user-1', 'operator');
        $action = new Action('a1', 'Test', initiator: $initiator);

        self::assertSame('user-1', $action->initiator()->partyId);
        self::assertEmpty($action->approvers());

        $approver = new PartySignature('mgr-1', 'manager', new \DateTimeImmutable());
        $action->addApprover($approver);

        self::assertCount(1, $action->approvers());
    }

    private function createAction(): Action
    {
        return new Action('action-1', 'Test action');
    }
}
