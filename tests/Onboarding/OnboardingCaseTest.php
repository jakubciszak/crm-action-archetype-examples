<?php

declare(strict_types=1);

namespace Tests\Onboarding;

use Onboarding\Domain\OnboardingCase;
use Onboarding\Domain\OnboardingState;
use Onboarding\Domain\ScenarioResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SharedKernel\Activity\Event\ProcessCompleted;
use SharedKernel\Activity\Event\StageAdvanced;
use SharedKernel\Activity\Event\StepCompleted;
use SharedKernel\Activity\Outcome;
use SharedKernel\Activity\OutcomeDirectiveType;
use SharedKernel\Activity\PartySignature;

final class OnboardingCaseTest extends TestCase
{
    #[Test]
    public function creates_from_enterprise_scenario(): void
    {
        $case = $this->createEnterpriseCase();

        self::assertSame(OnboardingState::Pending, $case->state());
        self::assertCount(4, $case->stages());
        self::assertSame('kyc', $case->currentStage()->stageCode());
    }

    #[Test]
    public function full_enterprise_onboarding_flow(): void
    {
        $case = $this->createEnterpriseCase();

        // KYC
        $case->startStep('kyc', 'kyc_doc_verification');
        self::assertSame(OnboardingState::InProgress, $case->state());

        $vendorSig = new PartySignature('kyc_vendor', 'vendor');
        $directives = $case->completeStep(
            'kyc', 'kyc_doc_verification',
            new Outcome('accepted', 'OK', approver: $vendorSig),
            $vendorSig,
        );
        self::assertSame(OutcomeDirectiveType::AdvanceStage, $directives[0]->type);
        self::assertSame('contract', $case->currentStage()->stageCode());

        // Contract
        $case->startStep('contract', 'contract_signing');
        $directives = $case->completeStep(
            'contract', 'contract_signing',
            new Outcome('signed', 'OK'),
        );
        self::assertSame(OutcomeDirectiveType::AdvanceStage, $directives[0]->type);
        self::assertSame('provisioning', $case->currentStage()->stageCode());

        // Provisioning
        $case->startStep('provisioning', 'env_provisioning');
        $directives = $case->completeStep(
            'provisioning', 'env_provisioning',
            new Outcome('provisioned', 'OK'),
        );
        self::assertSame(OutcomeDirectiveType::AdvanceStage, $directives[0]->type);
        self::assertSame('activation', $case->currentStage()->stageCode());

        // Activation
        $case->startStep('activation', 'account_activation');
        $directives = $case->completeStep(
            'activation', 'account_activation',
            new Outcome('activated', 'OK'),
        );
        self::assertSame(OutcomeDirectiveType::CompleteProcess, $directives[0]->type);
        self::assertTrue($case->isComplete());

        // Events
        $events = $case->releaseEvents();
        $stepCompleted = array_filter($events, fn($e) => $e instanceof StepCompleted);
        $stageAdvanced = array_filter($events, fn($e) => $e instanceof StageAdvanced);
        $processCompleted = array_filter($events, fn($e) => $e instanceof ProcessCompleted);

        self::assertCount(4, $stepCompleted);
        self::assertCount(3, $stageAdvanced);
        self::assertCount(1, $processCompleted);
    }

    #[Test]
    public function kyc_rejection_fails_process(): void
    {
        $case = $this->createEnterpriseCase();

        $case->startStep('kyc', 'kyc_doc_verification');
        $directives = $case->completeStep(
            'kyc', 'kyc_doc_verification',
            new Outcome('rejected', 'Dokumenty odrzucone'),
        );

        self::assertSame(OutcomeDirectiveType::FailProcess, $directives[0]->type);
        self::assertSame(OnboardingState::Failed, $case->state());
    }

    #[Test]
    public function sme_onboarding_has_two_stages(): void
    {
        $resolver = new ScenarioResolver();
        $scenario = $resolver->resolve('sme');
        $case = OnboardingCase::fromScenario('CASE-SME', 'Small Corp', 'sme', $scenario);

        self::assertCount(2, $case->stages());
        self::assertSame('kyc', $case->currentStage()->stageCode());
    }

    #[Test]
    public function sme_full_flow(): void
    {
        $resolver = new ScenarioResolver();
        $scenario = $resolver->resolve('sme');
        $case = OnboardingCase::fromScenario('CASE-SME', 'Small Corp', 'sme', $scenario);

        $case->startStep('kyc', 'auto_kyc');
        $case->completeStep('kyc', 'auto_kyc', new Outcome('accepted', 'KYC OK'));

        $case->startStep('activation', 'account_activation');
        $case->completeStep('activation', 'account_activation', new Outcome('activated', 'OK'));

        self::assertTrue($case->isComplete());
    }

    #[Test]
    public function throws_on_unknown_stage(): void
    {
        $case = $this->createEnterpriseCase();

        $this->expectException(\DomainException::class);
        $case->startStep('nonexistent', 'step');
    }

    #[Test]
    public function throws_on_unknown_step(): void
    {
        $case = $this->createEnterpriseCase();

        $this->expectException(\DomainException::class);
        $case->startStep('kyc', 'nonexistent');
    }

    private function createEnterpriseCase(): OnboardingCase
    {
        $resolver = new ScenarioResolver();
        $scenario = $resolver->resolve('enterprise');
        return OnboardingCase::fromScenario('CASE-001', 'Acme Corp', 'enterprise', $scenario);
    }
}
