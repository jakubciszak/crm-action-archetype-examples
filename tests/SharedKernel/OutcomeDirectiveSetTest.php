<?php

declare(strict_types=1);

namespace Tests\SharedKernel;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SharedKernel\Activity\OutcomeDirective;
use SharedKernel\Activity\OutcomeDirectiveSet;
use SharedKernel\Activity\OutcomeDirectiveType;
use SharedKernel\Activity\Policy\TerminalWinsPolicy;

final class OutcomeDirectiveSetTest extends TestCase
{
    #[Test]
    public function resolves_single_directive_without_conflict(): void
    {
        $set = new OutcomeDirectiveSet([OutcomeDirective::advance()]);
        $resolved = $set->resolve(new TerminalWinsPolicy());

        self::assertCount(1, $resolved);
        self::assertSame(OutcomeDirectiveType::AdvanceStage, $resolved[0]->type);
    }

    #[Test]
    public function returns_all_directives_via_getter(): void
    {
        $directives = [OutcomeDirective::advance(), OutcomeDirective::complete()];
        $set = new OutcomeDirectiveSet($directives);

        self::assertCount(2, $set->directives());
    }

    #[Test]
    public function resolves_with_terminal_wins_policy(): void
    {
        $set = new OutcomeDirectiveSet([
            OutcomeDirective::advance(),
            OutcomeDirective::fail('reason'),
        ]);
        $resolved = $set->resolve(new TerminalWinsPolicy());

        self::assertCount(1, $resolved);
        self::assertSame(OutcomeDirectiveType::FailProcess, $resolved[0]->type);
    }
}
