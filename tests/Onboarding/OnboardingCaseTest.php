<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Onboarding;

use CrmArchetype\Archetype\Outcome;
use CrmArchetype\Archetype\PartySignature;
use CrmArchetype\Onboarding\OnboardingCase;
use CrmArchetype\Onboarding\OnboardingPhase;
use CrmArchetype\Onboarding\OnboardingStep;
use CrmArchetype\Onboarding\StepBlueprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OnboardingCaseTest extends TestCase
{
    #[Test]
    public function full_onboarding_flow_with_phases(): void
    {
        $case = new OnboardingCase(
            title: 'Onboarding Acme Corp',
            briefDescription: 'Enterprise onboarding',
            raisedBy: 'sales-1',
            priority: 'high',
        );

        // Phase 1: KYC
        $kycPhase = new OnboardingPhase('KYC', 'Weryfikacja KYC');
        $kycStep = OnboardingStep::fromBlueprint(
            new StepBlueprint('kyc', 'Weryfikacja dokumentów', ['Zaakceptowany', 'DoUzupelnienia', 'Odrzucony']),
            new PartySignature('op-1', 'compliance'),
        );
        $kycPhase->addStep($kycStep);
        $case->addPhase($kycPhase);

        // Phase 2: Contract
        $contractPhase = new OnboardingPhase('Umowa', 'Podpisanie umowy');
        $contractStep = OnboardingStep::fromBlueprint(
            new StepBlueprint('contract_sign', 'Podpisanie umowy', ['Podpisano', 'Odrzucono']),
            new PartySignature('op-2', 'legal'),
        );
        $contractPhase->addStep($contractStep);
        $case->addPhase($contractPhase);

        self::assertTrue($case->isOpen());
        self::assertCount(2, $case->phases());
        self::assertSame($kycPhase, $case->currentPhase());
        self::assertFalse($case->allPhasesCompleted());

        // Complete KYC phase
        $kycStep->submit();
        $kycStep->start();
        $kycStep->complete(new Outcome('Zaakceptowany'));
        self::assertTrue($kycPhase->allStepsCompleted());
        $kycPhase->close();

        // Now current phase should be Contract
        self::assertSame($contractPhase, $case->currentPhase());

        // Complete Contract phase
        $contractStep->submit();
        $contractStep->start();
        $contractStep->complete(new Outcome('Podpisano'));
        $contractPhase->close();

        self::assertTrue($case->allPhasesCompleted());
        self::assertNull($case->currentPhase());

        $case->close();
        self::assertFalse($case->isOpen());
    }

    #[Test]
    public function phase_tracks_supplement_requirement(): void
    {
        $phase = new OnboardingPhase('KYC', 'Weryfikacja');
        $step = OnboardingStep::fromBlueprint(
            new StepBlueprint('kyc', 'Verify', ['OK', 'DoUzupelnienia']),
            new PartySignature('op', 'compliance'),
        );
        $phase->addStep($step);

        self::assertFalse($phase->hasSupplementRequired());

        $step->recordOutcome(new Outcome('DoUzupelnienia', reason: 'Missing docs'));

        self::assertTrue($phase->hasSupplementRequired());
    }

    #[Test]
    public function empty_phase_is_not_completed(): void
    {
        $phase = new OnboardingPhase('Empty');

        self::assertFalse($phase->allStepsCompleted());
    }

    #[Test]
    public function empty_case_has_no_phases_completed(): void
    {
        $case = new OnboardingCase('Test', 'desc', 'user-1');

        self::assertFalse($case->allPhasesCompleted());
        self::assertNull($case->currentPhase());
    }

    #[Test]
    public function phases_are_also_registered_as_threads(): void
    {
        $case = new OnboardingCase('Test', 'desc', 'user-1');
        $phase = new OnboardingPhase('KYC');
        $case->addPhase($phase);

        self::assertCount(1, $case->threads());
        self::assertCount(1, $case->phases());
    }
}
