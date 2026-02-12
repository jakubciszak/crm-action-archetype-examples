<?php

declare(strict_types=1);

namespace Tests\Loyalty;

use Loyalty\Domain\IncentiveAction;
use Loyalty\Domain\IncentiveActionState;
use Loyalty\Domain\IncentiveDecision;
use Loyalty\Domain\IncentiveRule;
use Loyalty\Domain\JournalEntry;
use Loyalty\Domain\PointsDebited;
use Loyalty\Domain\PointsGranted;
use Loyalty\Domain\RewardGrant;
use Loyalty\Domain\RewardGranted;
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
    public function evaluates_with_matching_rule(): void
    {
        $action = $this->createAction();
        $rule = $this->createMatchingRule(25);

        $action->evaluate($rule);

        self::assertSame(IncentiveActionState::AwaitingSettlement, $action->state());
        self::assertNotNull($action->decision());
    }

    #[Test]
    public function rejects_when_no_rule_matches(): void
    {
        $action = $this->createAction();
        $rule = $this->createNonMatchingRule();

        $action->evaluate($rule);

        self::assertSame(IncentiveActionState::Rejected, $action->state());
        self::assertNull($action->decision());
    }

    #[Test]
    public function settles_after_evaluation(): void
    {
        $action = $this->createAction();
        $rule = $this->createMatchingRule(25);

        $action->evaluate($rule);
        $action->settle();

        self::assertSame(IncentiveActionState::Settled, $action->state());

        $events = $action->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsGranted::class, $events[0]);
    }

    #[Test]
    public function settle_with_reward(): void
    {
        $action = $this->createAction();
        $rule = $this->createMatchingRuleWithReward(150);

        $action->evaluate($rule);
        $action->settle();

        $events = $action->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(PointsGranted::class, $events[0]);
        self::assertInstanceOf(RewardGranted::class, $events[1]);
    }

    #[Test]
    public function cannot_settle_without_decision(): void
    {
        $action = $this->createAction();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot settle without decision');
        $action->settle();
    }

    #[Test]
    public function reverses_settled_action(): void
    {
        $action = $this->createAction();
        $rule = $this->createMatchingRule(25);

        $action->evaluate($rule);
        $action->settle();
        $action->releaseEvents(); // clear settle events

        $action->reverse('Chargeback');

        self::assertSame(IncentiveActionState::Reversed, $action->state());
        $events = $action->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsDebited::class, $events[0]);
        self::assertSame('Chargeback', $events[0]->reason);
    }

    #[Test]
    public function cannot_reverse_non_settled_action(): void
    {
        $action = $this->createAction();
        $rule = $this->createMatchingRule(25);
        $action->evaluate($rule);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Can only reverse settled actions');
        $action->reverse('reason');
    }

    #[Test]
    public function cannot_transition_received_to_settled(): void
    {
        $action = $this->createAction();

        $this->expectException(\DomainException::class);
        $action->settle();
    }

    #[Test]
    public function accessors_return_correct_values(): void
    {
        $action = $this->createAction();

        self::assertSame('ACT-001', $action->id());
        self::assertSame('order_placed', $action->actionType());
        self::assertSame('USR-001', $action->participantId());
        self::assertArrayHasKey('totalAmountCents', $action->payload());
    }

    private function createAction(): IncentiveAction
    {
        return new IncentiveAction(
            id: 'ACT-001',
            actionType: 'order_placed',
            payload: ['totalAmountCents' => 25000, 'orderId' => 'ORD-001'],
            participantId: 'USR-001',
            occurredAt: new \DateTimeImmutable(),
        );
    }

    private function createMatchingRule(int $points): IncentiveRule
    {
        return new class($points) implements IncentiveRule {
            public function __construct(private readonly int $points) {}

            public function supports(string $actionType): bool
            {
                return $actionType === 'order_placed';
            }

            public function evaluate(IncentiveAction $action): IncentiveDecision
            {
                return new IncentiveDecision(
                    journalEntries: [new JournalEntry(points: $this->points, reason: 'Test order')],
                    rewardGrants: [],
                );
            }
        };
    }

    private function createMatchingRuleWithReward(int $points): IncentiveRule
    {
        return new class($points) implements IncentiveRule {
            public function __construct(private readonly int $points) {}

            public function supports(string $actionType): bool
            {
                return $actionType === 'order_placed';
            }

            public function evaluate(IncentiveAction $action): IncentiveDecision
            {
                return new IncentiveDecision(
                    journalEntries: [new JournalEntry(points: $this->points, reason: 'Test order')],
                    rewardGrants: [new RewardGrant('free_shipping', 'Darmowa dostawa')],
                );
            }
        };
    }

    private function createNonMatchingRule(): IncentiveRule
    {
        return new class implements IncentiveRule {
            public function supports(string $actionType): bool
            {
                return false;
            }

            public function evaluate(IncentiveAction $action): IncentiveDecision
            {
                throw new \LogicException('Should not be called');
            }
        };
    }
}
