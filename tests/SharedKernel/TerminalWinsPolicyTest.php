<?php

declare(strict_types=1);

namespace Tests\SharedKernel;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SharedKernel\Activity\OutcomeDirective;
use SharedKernel\Activity\OutcomeDirectiveType;
use SharedKernel\Activity\Policy\TerminalWinsPolicy;

final class TerminalWinsPolicyTest extends TestCase
{
    private TerminalWinsPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new TerminalWinsPolicy();
    }

    #[Test]
    public function single_advance_passes_through(): void
    {
        $result = $this->policy->resolve([OutcomeDirective::advance()]);

        self::assertCount(1, $result);
        self::assertSame(OutcomeDirectiveType::AdvanceStage, $result[0]->type);
    }

    #[Test]
    public function fail_wins_over_advance(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::advance(),
            OutcomeDirective::fail('KYC failed'),
        ]);

        self::assertCount(1, $result);
        self::assertSame(OutcomeDirectiveType::FailProcess, $result[0]->type);
    }

    #[Test]
    public function escalate_wins_over_advance(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::advance(),
            OutcomeDirective::escalate(),
        ]);

        self::assertCount(1, $result);
        self::assertSame(OutcomeDirectiveType::Escalate, $result[0]->type);
    }

    #[Test]
    public function fail_wins_over_escalate(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::escalate(),
            OutcomeDirective::fail('reason'),
        ]);

        self::assertCount(1, $result);
        self::assertSame(OutcomeDirectiveType::FailProcess, $result[0]->type);
    }

    #[Test]
    public function terminal_preserves_spawns(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::fail('reason'),
            OutcomeDirective::spawnStep('extra_check', 'kyc'),
        ]);

        self::assertCount(2, $result);
        self::assertSame(OutcomeDirectiveType::FailProcess, $result[0]->type);
        self::assertSame(OutcomeDirectiveType::SpawnStep, $result[1]->type);
    }

    #[Test]
    public function non_terminal_conflict_resolves_by_priority(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::advance(),
            OutcomeDirective::retry('step_code'),
        ]);

        self::assertCount(1, $result);
        self::assertSame(OutcomeDirectiveType::RetryStep, $result[0]->type);
    }

    #[Test]
    public function no_conflict_returns_all(): void
    {
        $result = $this->policy->resolve([
            OutcomeDirective::advance(),
            OutcomeDirective::spawnStep('extra', 'stage'),
        ]);

        self::assertCount(2, $result);
    }
}
