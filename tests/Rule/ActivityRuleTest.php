<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Rule;

use CrmArchetype\Rule\ActivityRule;
use CrmArchetype\Rule\Proposition;
use CrmArchetype\Rule\Rule;
use CrmArchetype\Rule\RuleContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActivityRuleTest extends TestCase
{
    #[Test]
    public function activity_is_triggered_when_rule_matches(): void
    {
        $rule = new class extends ActivityRule {
            public bool $activityCalled = false;

            public function rule(): Rule
            {
                return new Rule('EscalateKYC', [
                    new Proposition('kycDays', '>', 5),
                    new Proposition('isEnterprise', '==', true),
                ]);
            }

            protected function activity(): mixed
            {
                $this->activityCalled = true;

                return 'escalated';
            }
        };

        $ctx = (new RuleContext())->set('kycDays', 7)->set('isEnterprise', true);

        $result = $rule->evaluate($ctx);

        self::assertTrue($result);
        self::assertTrue($rule->activityCalled);
    }

    #[Test]
    public function activity_is_not_triggered_when_rule_does_not_match(): void
    {
        $rule = new class extends ActivityRule {
            public bool $activityCalled = false;

            public function rule(): Rule
            {
                return new Rule('DoublePoints', [
                    new Proposition('isGoldMember', '==', true),
                    new Proposition('orderPLN', '>', 500),
                ]);
            }

            protected function activity(): mixed
            {
                $this->activityCalled = true;

                return 'doubled';
            }
        };

        $ctx = (new RuleContext())->set('isGoldMember', false)->set('orderPLN', 750);

        $result = $rule->evaluate($ctx);

        self::assertFalse($result);
        self::assertFalse($rule->activityCalled);
    }
}
