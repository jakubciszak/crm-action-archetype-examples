<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Loyalty;

use CrmArchetype\Loyalty\IncentiveAction;
use CrmArchetype\Loyalty\IncentiveActionState;
use CrmArchetype\Loyalty\IncentiveDecision;
use CrmArchetype\Loyalty\InvalidIncentiveStateTransitionException;
use CrmArchetype\Loyalty\JournalEntry;
use CrmArchetype\Loyalty\RewardGrant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IncentiveActionTest extends TestCase
{
    #[Test]
    public function starts_in_received_state(): void
    {
        $action = $this->createAction();

        self::assertSame(IncentiveActionState::Received, $action->state());
    }

    #[Test]
    public function happy_path_received_to_settled(): void
    {
        $action = $this->createAction();

        $action->evaluate();
        self::assertSame(IncentiveActionState::Evaluating, $action->state());

        $decision = new IncentiveDecision(
            description: 'PointsGranted',
            journalEntries: [new JournalEntry('acc-1', 150, 'Purchase reward')],
        );

        $action->settle($decision);
        self::assertSame(IncentiveActionState::Settled, $action->state());
        self::assertCount(1, $action->decisions());
        self::assertSame('PointsGranted', $action->decisions()[0]->description);
    }

    #[Test]
    public function settle_with_reward_grant(): void
    {
        $action = $this->createAction();
        $action->evaluate();

        $decision = new IncentiveDecision(
            description: 'RewardGrant',
            rewardGrants: [new RewardGrant('reward-1', 'member-1', 'Free coffee')],
            domainEvents: [(object) ['type' => 'RewardGranted', 'rewardId' => 'reward-1']],
        );

        $action->settle($decision);

        self::assertSame(IncentiveActionState::Settled, $action->state());
        self::assertNotEmpty($action->decisions()[0]->rewardGrants);

        $events = $action->releasedEvents();
        self::assertCount(1, $events);
        self::assertSame('RewardGranted', $events[0]->type);

        // Events are cleared after release
        self::assertEmpty($action->releasedEvents());
    }

    #[Test]
    public function rejection_from_evaluating(): void
    {
        $action = $this->createAction();
        $action->evaluate();

        $action->reject('Duplicate transaction');

        self::assertSame(IncentiveActionState::Rejected, $action->state());
        self::assertSame('Duplicate transaction', $action->rejectionReason());
    }

    #[Test]
    public function reversal_from_settled_chargeback(): void
    {
        $action = $this->createAction();
        $action->evaluate();
        $action->settle(new IncentiveDecision('PointsGranted', [new JournalEntry('acc-1', 100, 'Points')]));

        $action->reverse('Chargeback');

        self::assertSame(IncentiveActionState::Reversed, $action->state());
        self::assertSame('Chargeback', $action->reversalReason());
    }

    #[Test]
    public function cannot_settle_from_received(): void
    {
        $action = $this->createAction();

        $this->expectException(InvalidIncentiveStateTransitionException::class);
        $action->settle(new IncentiveDecision('test'));
    }

    #[Test]
    public function cannot_reverse_from_evaluating(): void
    {
        $action = $this->createAction();
        $action->evaluate();

        $this->expectException(InvalidIncentiveStateTransitionException::class);
        $action->reverse('no reason');
    }

    #[Test]
    public function cannot_reject_from_received(): void
    {
        $action = $this->createAction();

        $this->expectException(InvalidIncentiveStateTransitionException::class);
        $action->reject('too early');
    }

    #[Test]
    public function cannot_evaluate_twice(): void
    {
        $action = $this->createAction();
        $action->evaluate();

        $this->expectException(InvalidIncentiveStateTransitionException::class);
        $action->evaluate();
    }

    #[Test]
    public function accessors(): void
    {
        $action = $this->createAction();

        self::assertSame('ia-1', $action->id());
        self::assertSame('Purchase: 500 PLN', $action->description());
        self::assertSame('member-1', $action->memberId());
        self::assertSame('purchases', $action->category());
    }

    private function createAction(): IncentiveAction
    {
        return new IncentiveAction(
            id: 'ia-1',
            description: 'Purchase: 500 PLN',
            memberId: 'member-1',
            category: 'purchases',
        );
    }
}
