<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Lifecycle;

use CrmArchetype\Lifecycle\LifecycleResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LifecycleResultTest extends TestCase
{
    #[Test]
    public function completed_result(): void
    {
        $result = LifecycleResult::completed('Step done');

        self::assertTrue($result->isCompleted());
        self::assertFalse($result->isAwaitingCallback());
        self::assertFalse($result->isFailed());
        self::assertSame('Step done', $result->message);
        self::assertNull($result->externalRef);
    }

    #[Test]
    public function awaiting_callback_result(): void
    {
        $result = LifecycleResult::awaitingCallback('kyc-ref-123');

        self::assertTrue($result->isAwaitingCallback());
        self::assertFalse($result->isCompleted());
        self::assertSame('kyc-ref-123', $result->externalRef);
    }

    #[Test]
    public function failed_result(): void
    {
        $result = LifecycleResult::failed('Vendor unavailable');

        self::assertTrue($result->isFailed());
        self::assertFalse($result->isCompleted());
        self::assertSame('Vendor unavailable', $result->message);
    }
}
