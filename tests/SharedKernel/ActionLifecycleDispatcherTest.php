<?php

declare(strict_types=1);

namespace Tests\SharedKernel;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SharedKernel\Activity\Action;
use SharedKernel\Activity\ActionState;
use SharedKernel\Activity\Lifecycle\ActionLifecycleDispatcher;
use SharedKernel\Activity\Lifecycle\ActionLifecycleHandler;
use SharedKernel\Activity\Lifecycle\ActionLifecycleResult;
use SharedKernel\Activity\Lifecycle\ActionLifecycleStatus;

final class ActionLifecycleDispatcherTest extends TestCase
{
    #[Test]
    public function returns_completed_when_no_handlers(): void
    {
        $dispatcher = new ActionLifecycleDispatcher([]);
        $action = $this->createTestAction();

        $result = $dispatcher->dispatch($action, 'test', ActionState::Pending, ActionState::InProgress);

        self::assertSame(ActionLifecycleStatus::Completed, $result->status);
    }

    #[Test]
    public function dispatches_to_matching_handler(): void
    {
        $handler = new class implements ActionLifecycleHandler {
            public function supports(string $actionType, ActionState $from, ActionState $to): bool
            {
                return $actionType === 'kyc_doc_verification'
                    && $from === ActionState::Pending
                    && $to === ActionState::InProgress;
            }

            public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult
            {
                return ActionLifecycleResult::awaitingCallback('VRF-001');
            }
        };

        $dispatcher = new ActionLifecycleDispatcher([$handler]);
        $action = $this->createTestAction();

        $result = $dispatcher->dispatch($action, 'kyc_doc_verification', ActionState::Pending, ActionState::InProgress);

        self::assertSame(ActionLifecycleStatus::AwaitingCallback, $result->status);
        self::assertSame('VRF-001', $result->externalReference);
    }

    #[Test]
    public function skips_non_matching_handlers(): void
    {
        $handler = new class implements ActionLifecycleHandler {
            public function supports(string $actionType, ActionState $from, ActionState $to): bool
            {
                return $actionType === 'contract_signing';
            }

            public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult
            {
                return ActionLifecycleResult::failed('should not reach here');
            }
        };

        $dispatcher = new ActionLifecycleDispatcher([$handler]);
        $action = $this->createTestAction();

        $result = $dispatcher->dispatch($action, 'kyc_doc_verification', ActionState::Pending, ActionState::InProgress);

        self::assertSame(ActionLifecycleStatus::Completed, $result->status);
    }

    #[Test]
    public function handler_can_return_failed(): void
    {
        $handler = new class implements ActionLifecycleHandler {
            public function supports(string $actionType, ActionState $from, ActionState $to): bool
            {
                return true;
            }

            public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult
            {
                return ActionLifecycleResult::failed('Vendor unavailable');
            }
        };

        $dispatcher = new ActionLifecycleDispatcher([$handler]);
        $action = $this->createTestAction();

        $result = $dispatcher->dispatch($action, 'any', ActionState::Pending, ActionState::InProgress);

        self::assertSame(ActionLifecycleStatus::Failed, $result->status);
        self::assertSame('Vendor unavailable', $result->message);
    }

    private function createTestAction(): Action
    {
        return new class('test-1', 'test', new \DateTimeImmutable()) extends Action {};
    }
}
