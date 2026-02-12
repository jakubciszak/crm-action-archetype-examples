<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Lifecycle;

use CrmArchetype\Archetype\Action;
use CrmArchetype\Archetype\ActionState;
use CrmArchetype\Lifecycle\ActionLifecycleDispatcher;
use CrmArchetype\Lifecycle\ActionLifecycleHandler;
use CrmArchetype\Lifecycle\ActionLifecycleResult;
use CrmArchetype\Lifecycle\ActionLifecycleStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActionLifecycleDispatcherTest extends TestCase
{
    #[Test]
    public function returns_completed_when_no_handlers_registered(): void
    {
        $dispatcher = new ActionLifecycleDispatcher([]);
        $action = new Action('a1', 'Test');

        $result = $dispatcher->dispatch($action, 'some_type', ActionState::Pending, ActionState::InProgress);

        self::assertTrue($result->isCompleted());
    }

    #[Test]
    public function returns_completed_when_no_handler_matches(): void
    {
        $handler = $this->createHandler(
            supports: false,
            result: ActionLifecycleResult::completed(),
        );
        $dispatcher = new ActionLifecycleDispatcher([$handler]);
        $action = new Action('a1', 'Test');

        $result = $dispatcher->dispatch($action, 'unknown_type', ActionState::Pending, ActionState::InProgress);

        self::assertTrue($result->isCompleted());
    }

    #[Test]
    public function dispatches_to_matching_handler_and_returns_completed(): void
    {
        $handler = $this->createHandler(
            supports: true,
            result: ActionLifecycleResult::completed('Handler executed'),
        );
        $dispatcher = new ActionLifecycleDispatcher([$handler]);
        $action = new Action('a1', 'Test');

        $result = $dispatcher->dispatch($action, 'kyc_doc_verification', ActionState::Pending, ActionState::InProgress);

        self::assertTrue($result->isCompleted());
        self::assertSame('Handler executed', $result->message);
    }

    #[Test]
    public function stops_chain_on_awaiting_callback(): void
    {
        $handler1 = $this->createHandler(
            supports: true,
            result: ActionLifecycleResult::awaitingCallback('ref-123'),
        );
        $handler2 = $this->createHandler(
            supports: true,
            result: ActionLifecycleResult::completed('Should not be called'),
        );
        $dispatcher = new ActionLifecycleDispatcher([$handler1, $handler2]);
        $action = new Action('a1', 'Test');

        $result = $dispatcher->dispatch($action, 'kyc_doc_verification', ActionState::Pending, ActionState::InProgress);

        self::assertTrue($result->isAwaitingCallback());
        self::assertSame('ref-123', $result->externalReference);
    }

    #[Test]
    public function stops_chain_on_failed(): void
    {
        $handler1 = $this->createHandler(
            supports: true,
            result: ActionLifecycleResult::failed('Vendor unavailable'),
        );
        $handler2 = $this->createHandler(
            supports: true,
            result: ActionLifecycleResult::completed('Should not be called'),
        );
        $dispatcher = new ActionLifecycleDispatcher([$handler1, $handler2]);
        $action = new Action('a1', 'Test');

        $result = $dispatcher->dispatch($action, 'kyc_doc_verification', ActionState::Pending, ActionState::InProgress);

        self::assertTrue($result->isFailed());
        self::assertSame('Vendor unavailable', $result->message);
    }

    #[Test]
    public function first_matching_handler_wins(): void
    {
        $nonMatching = $this->createHandler(
            supports: false,
            result: ActionLifecycleResult::completed('Wrong handler'),
        );
        $matching = $this->createHandler(
            supports: true,
            result: ActionLifecycleResult::completed('Correct handler'),
        );
        $dispatcher = new ActionLifecycleDispatcher([$nonMatching, $matching]);
        $action = new Action('a1', 'Test');

        $result = $dispatcher->dispatch($action, 'kyc_doc_verification', ActionState::Pending, ActionState::InProgress);

        self::assertSame('Correct handler', $result->message);
    }

    #[Test]
    public function completed_result_has_metadata(): void
    {
        $handler = $this->createHandler(
            supports: true,
            result: ActionLifecycleResult::completed('Provisioned', ['tenantUrl' => 'https://tenant.example.com']),
        );
        $dispatcher = new ActionLifecycleDispatcher([$handler]);
        $action = new Action('a1', 'Test');

        $result = $dispatcher->dispatch($action, 'env_provisioning', ActionState::Pending, ActionState::InProgress);

        self::assertTrue($result->isCompleted());
        self::assertSame('https://tenant.example.com', $result->metadata['tenantUrl']);
    }

    #[Test]
    public function result_status_enum_is_set_correctly(): void
    {
        $completed = ActionLifecycleResult::completed();
        self::assertSame(ActionLifecycleStatus::Completed, $completed->status);

        $awaiting = ActionLifecycleResult::awaitingCallback('ref');
        self::assertSame(ActionLifecycleStatus::AwaitingCallback, $awaiting->status);

        $failed = ActionLifecycleResult::failed('error');
        self::assertSame(ActionLifecycleStatus::Failed, $failed->status);
    }

    private function createHandler(bool $supports, ActionLifecycleResult $result): ActionLifecycleHandler
    {
        return new class ($supports, $result) implements ActionLifecycleHandler {
            public function __construct(
                private readonly bool $shouldSupport,
                private readonly ActionLifecycleResult $result,
            ) {}

            public function supports(string $actionType, ActionState $from, ActionState $to): bool
            {
                return $this->shouldSupport;
            }

            public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult
            {
                return $this->result;
            }
        };
    }
}
