<?php

declare(strict_types=1);

namespace Tests\SharedKernel;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SharedKernel\Activity\OutcomeDirective;
use SharedKernel\Activity\OutcomeDirectiveType;
use SharedKernel\Activity\Policy\EscalateOnConflictPolicy;

final class EscalateOnConflictPolicyTest extends TestCase
{
    private EscalateOnConflictPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new EscalateOnConflictPolicy();
    }

    #[Test]
    public function single_directive_passes_through(): void
    {
        $result = $this->policy->resolve([OutcomeDirective::advance()]);

        self::assertCount(1, $result);
        self::assertSame(OutcomeDirectiveType::AdvanceStage, $result[0]->type);
    }

    #[Test]
    public function two_non_composable_directives_escalate(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::advance(),
            OutcomeDirective::retry('step_code'),
        ]);

        self::assertCount(1, $result);
        self::assertSame(OutcomeDirectiveType::Escalate, $result[0]->type);
    }

    #[Test]
    public function composable_with_non_composable_does_not_escalate(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::advance(),
            OutcomeDirective::spawnStep('extra', 'stage'),
        ]);

        self::assertCount(2, $result);
    }

    #[Test]
    public function multiple_spawns_with_one_non_composable_does_not_escalate(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::advance(),
            OutcomeDirective::spawnStep('extra1', 'stage'),
            OutcomeDirective::spawnStep('extra2', 'stage'),
        ]);

        self::assertCount(3, $result);
    }

    #[Test]
    public function fail_and_advance_escalates(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::advance(),
            OutcomeDirective::fail('reason'),
        ]);

        self::assertCount(1, $result);
        self::assertSame(OutcomeDirectiveType::Escalate, $result[0]->type);
    }
}
