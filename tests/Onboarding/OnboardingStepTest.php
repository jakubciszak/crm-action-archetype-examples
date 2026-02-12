<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Onboarding;

use CrmArchetype\Archetype\ActionState;
use CrmArchetype\Archetype\Outcome;
use CrmArchetype\Archetype\PartySignature;
use CrmArchetype\Onboarding\OnboardingStep;
use CrmArchetype\Onboarding\StepBlueprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OnboardingStepTest extends TestCase
{
    #[Test]
    public function creates_from_blueprint_with_possible_outcomes(): void
    {
        $step = $this->createKycStep();

        self::assertSame('kyc_verify', $step->id());
        self::assertSame('Weryfikacja dokumentów KYC', $step->description());
        self::assertCount(3, $step->possibleOutcomes());
        self::assertSame('Zaakceptowany', $step->possibleOutcomes()[0]->description);
        self::assertSame('DoUzupelnienia', $step->possibleOutcomes()[1]->description);
        self::assertSame('Odrzucony', $step->possibleOutcomes()[2]->description);
    }

    #[Test]
    public function inherits_action_state_machine(): void
    {
        $step = $this->createKycStep();

        self::assertSame(ActionState::Draft, $step->state());

        $step->submit();
        $step->start();

        self::assertSame(ActionState::InProgress, $step->state());
    }

    #[Test]
    public function requires_supplement_when_outcome_is_do_uzupelnienia(): void
    {
        $step = $this->createKycStep();

        self::assertFalse($step->requiresSupplement());

        $step->recordOutcome(new Outcome('DoUzupelnienia', reason: 'Brak KRS'));

        self::assertTrue($step->requiresSupplement());
    }

    #[Test]
    public function is_accepted_when_outcome_is_zaakceptowany(): void
    {
        $step = $this->createKycStep();

        self::assertFalse($step->isAccepted());

        $step->recordOutcome(new Outcome('Zaakceptowany'));

        self::assertTrue($step->isAccepted());
    }

    #[Test]
    public function is_rejected_when_outcome_is_odrzucony(): void
    {
        $step = $this->createKycStep();

        self::assertFalse($step->isRejected());

        $step->recordOutcome(new Outcome('Odrzucony', reason: 'Fraudulent documents'));

        self::assertTrue($step->isRejected());
    }

    #[Test]
    public function full_kyc_flow_accepted(): void
    {
        $step = $this->createKycStep();

        // Draft → Pending → InProgress → Completed
        $step->submit();
        $step->start();
        $step->complete(new Outcome('Zaakceptowany'));

        self::assertSame(ActionState::Completed, $step->state());
        self::assertTrue($step->isAccepted());
        self::assertFalse($step->requiresSupplement());
    }

    #[Test]
    public function full_kyc_flow_with_supplement_loop(): void
    {
        $step = $this->createKycStep();

        $step->submit();
        $step->start();

        // First attempt — requires supplement
        $step->recordOutcome(new Outcome('DoUzupelnienia', reason: 'Brak KRS'));
        self::assertTrue($step->requiresSupplement());

        // Step still InProgress, awaiting new data
        self::assertSame(ActionState::InProgress, $step->state());

        // After supplement received, complete
        $step->complete(new Outcome('Zaakceptowany'));
        self::assertSame(ActionState::Completed, $step->state());
    }

    #[Test]
    public function preserves_initiator_from_blueprint(): void
    {
        $step = $this->createKycStep();

        self::assertNotNull($step->initiator());
        self::assertSame('operator-1', $step->initiator()->partyId);
        self::assertSame('compliance_officer', $step->initiator()->role);
    }

    private function createKycStep(): OnboardingStep
    {
        $blueprint = new StepBlueprint(
            stepCode: 'kyc_verify',
            description: 'Weryfikacja dokumentów KYC',
            possibleOutcomes: ['Zaakceptowany', 'DoUzupelnienia', 'Odrzucony'],
        );

        return OnboardingStep::fromBlueprint(
            $blueprint,
            new PartySignature('operator-1', 'compliance_officer'),
        );
    }
}
