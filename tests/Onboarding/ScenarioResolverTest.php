<?php

declare(strict_types=1);

namespace Tests\Onboarding;

use Onboarding\Domain\ScenarioResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SharedKernel\Activity\Policy\EscalateOnConflictPolicy;
use SharedKernel\Activity\Policy\TerminalWinsPolicy;

final class ScenarioResolverTest extends TestCase
{
    private ScenarioResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ScenarioResolver();
    }

    #[Test]
    public function resolves_enterprise_scenario(): void
    {
        $scenario = $this->resolver->resolve('enterprise');

        self::assertSame('enterprise_onboarding', $scenario->scenarioCode);
        self::assertCount(4, $scenario->stages);
        self::assertInstanceOf(EscalateOnConflictPolicy::class, $scenario->conflictPolicy);

        $stageCodes = array_map(fn($s) => $s->stageCode, $scenario->stages);
        self::assertSame(['kyc', 'contract', 'provisioning', 'activation'], $stageCodes);
    }

    #[Test]
    public function resolves_sme_scenario(): void
    {
        $scenario = $this->resolver->resolve('sme');

        self::assertSame('sme_onboarding', $scenario->scenarioCode);
        self::assertCount(2, $scenario->stages);
        self::assertInstanceOf(TerminalWinsPolicy::class, $scenario->conflictPolicy);

        $stageCodes = array_map(fn($s) => $s->stageCode, $scenario->stages);
        self::assertSame(['kyc', 'activation'], $stageCodes);
    }

    #[Test]
    public function throws_on_unknown_client_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown client type: unknown');

        $this->resolver->resolve('unknown');
    }

    #[Test]
    public function enterprise_kyc_stage_has_four_outcomes(): void
    {
        $scenario = $this->resolver->resolve('enterprise');
        $kycStage = $scenario->stages[0];

        self::assertSame('kyc', $kycStage->stageCode);
        self::assertCount(1, $kycStage->steps);

        $kycStep = $kycStage->steps[0];
        self::assertSame('kyc_doc_verification', $kycStep->stepCode);
        self::assertCount(4, $kycStep->possibleOutcomes);
    }
}
