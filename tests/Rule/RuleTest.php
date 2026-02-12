<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Rule;

use CrmArchetype\Rule\Proposition;
use CrmArchetype\Rule\Rule;
use CrmArchetype\Rule\RuleContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuleTest extends TestCase
{
    #[Test]
    public function proposition_evaluates_greater_than(): void
    {
        $prop = new Proposition('kycDays', '>', 5);

        self::assertTrue($prop->evaluate(6));
        self::assertFalse($prop->evaluate(5));
        self::assertFalse($prop->evaluate(3));
    }

    #[Test]
    public function proposition_evaluates_equality(): void
    {
        $prop = new Proposition('isEnterprise', '==', true);

        self::assertTrue($prop->evaluate(true));
        self::assertFalse($prop->evaluate(false));
    }

    #[Test]
    public function proposition_evaluates_less_than_or_equal(): void
    {
        $prop = new Proposition('score', '<=', 100);

        self::assertTrue($prop->evaluate(100));
        self::assertTrue($prop->evaluate(50));
        self::assertFalse($prop->evaluate(101));
    }

    #[Test]
    public function unknown_operator_throws_exception(): void
    {
        $prop = new Proposition('x', '~', 5);

        $this->expectException(\InvalidArgumentException::class);
        $prop->evaluate(5);
    }

    #[Test]
    public function rule_context_stores_and_retrieves_variables(): void
    {
        $ctx = new RuleContext();
        $ctx->set('kycDays', 7)->set('isEnterprise', true);

        self::assertSame(7, $ctx->get('kycDays'));
        self::assertTrue($ctx->get('isEnterprise'));
        self::assertTrue($ctx->has('kycDays'));
        self::assertFalse($ctx->has('unknown'));
    }

    #[Test]
    public function rule_context_throws_on_missing_variable(): void
    {
        $ctx = new RuleContext();

        $this->expectException(\InvalidArgumentException::class);
        $ctx->get('nonexistent');
    }

    #[Test]
    public function rule_evaluates_all_propositions_must_pass(): void
    {
        // EscalateKYC: IF kycDays > 5 AND isEnterprise == true (slide 23)
        $rule = new Rule('EscalateKYC', [
            new Proposition('kycDays', '>', 5),
            new Proposition('isEnterprise', '==', true),
        ]);

        $ctx = (new RuleContext())->set('kycDays', 7)->set('isEnterprise', true);
        self::assertTrue($rule->evaluate($ctx));

        $ctx2 = (new RuleContext())->set('kycDays', 3)->set('isEnterprise', true);
        self::assertFalse($rule->evaluate($ctx2));

        $ctx3 = (new RuleContext())->set('kycDays', 7)->set('isEnterprise', false);
        self::assertFalse($rule->evaluate($ctx3));
    }

    #[Test]
    public function double_points_rule_from_presentation(): void
    {
        // DoublePoints: IF isGoldMember AND orderPLN > 500 (slide 23)
        $rule = new Rule('DoublePoints', [
            new Proposition('isGoldMember', '==', true),
            new Proposition('orderPLN', '>', 500),
        ]);

        $ctx = (new RuleContext())->set('isGoldMember', true)->set('orderPLN', 750);
        self::assertTrue($rule->evaluate($ctx));

        $ctx2 = (new RuleContext())->set('isGoldMember', false)->set('orderPLN', 750);
        self::assertFalse($rule->evaluate($ctx2));

        $ctx3 = (new RuleContext())->set('isGoldMember', true)->set('orderPLN', 300);
        self::assertFalse($rule->evaluate($ctx3));
    }

    #[Test]
    public function rule_name_and_propositions(): void
    {
        $props = [new Proposition('x', '>', 1)];
        $rule = new Rule('TestRule', $props);

        self::assertSame('TestRule', $rule->name());
        self::assertCount(1, $rule->propositions());
    }
}
