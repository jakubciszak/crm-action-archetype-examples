<?php

declare(strict_types=1);

namespace Tests\Onboarding;

use Onboarding\Domain\OnboardingStep;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SharedKernel\Activity\ActionState;
use SharedKernel\Activity\Outcome;
use SharedKernel\Activity\OutcomeBlueprint;
use SharedKernel\Activity\OutcomeDirective;
use SharedKernel\Activity\OutcomeDirectiveType;
use SharedKernel\Activity\StepBlueprint;

final class OnboardingStepTest extends TestCase
{
    #[Test]
    public function creates_from_blueprint(): void
    {
        $step = $this->createKycStep();

        self::assertSame(ActionState::Draft, $step->state());
        self::assertSame('kyc_doc_verification', $step->type());
        self::assertCount(4, $step->possibleOutcomes());
    }

    #[Test]
    public function transitions_draft_to_pending_to_in_progress(): void
    {
        $step = $this->createKycStep();

        $step->transitionTo(ActionState::Pending);
        self::assertSame(ActionState::Pending, $step->state());

        $step->transitionTo(ActionState::InProgress);
        self::assertSame(ActionState::InProgress, $step->state());
    }

    #[Test]
    public function cannot_transition_from_draft_to_in_progress(): void
    {
        $step = $this->createKycStep();

        $this->expectException(\DomainException::class);
        $step->transitionTo(ActionState::InProgress);
    }

    #[Test]
    public function completes_with_valid_outcome(): void
    {
        $step = $this->createKycStep();
        $step->transitionTo(ActionState::Pending);
        $step->transitionTo(ActionState::InProgress);

        $outcome = new Outcome('accepted', 'Dokumenty zatwierdzone');
        $directiveSet = $step->complete($outcome);
        $directives = $directiveSet->directives();

        self::assertSame(ActionState::Completed, $step->state());
        self::assertCount(1, $directives);
        self::assertSame(OutcomeDirectiveType::AdvanceStage, $directives[0]->type);
    }

    #[Test]
    public function rejects_unknown_outcome(): void
    {
        $step = $this->createKycStep();
        $step->transitionTo(ActionState::Pending);
        $step->transitionTo(ActionState::InProgress);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Outcome 'unknown' is not in possibleOutcomes");
        $step->complete(new Outcome('unknown', 'Unknown'));
    }

    #[Test]
    public function requires_supplement_detects_outcome(): void
    {
        $step = $this->createKycStep();
        $step->transitionTo(ActionState::Pending);
        $step->transitionTo(ActionState::InProgress);

        $step->complete(new Outcome('needs_supplement', 'Brakujące dokumenty'));

        self::assertTrue($step->requiresSupplement());
    }

    #[Test]
    public function is_rejected_detects_outcome(): void
    {
        $step = $this->createKycStep();
        $step->transitionTo(ActionState::Pending);
        $step->transitionTo(ActionState::InProgress);

        $step->complete(new Outcome('rejected', 'Dokumenty odrzucone'));

        self::assertTrue($step->isRejected());
    }

    #[Test]
    public function is_not_rejected_for_accepted(): void
    {
        $step = $this->createKycStep();
        $step->transitionTo(ActionState::Pending);
        $step->transitionTo(ActionState::InProgress);

        $step->complete(new Outcome('accepted', 'OK'));

        self::assertFalse($step->isRejected());
        self::assertFalse($step->requiresSupplement());
    }

    private function createKycStep(): OnboardingStep
    {
        $blueprint = new StepBlueprint('kyc_doc_verification', 'Weryfikacja dokumentów', [
            new OutcomeBlueprint('accepted', 'Dokumenty zatwierdzone', OutcomeDirective::advance()),
            new OutcomeBlueprint('needs_supplement', 'Brakujące dokumenty', OutcomeDirective::retry('kyc_doc_verification')),
            new OutcomeBlueprint('rejected', 'Dokumenty odrzucone', OutcomeDirective::fail('KYC failed')),
            new OutcomeBlueprint('suspicious', 'Podejrzenie oszustwa', OutcomeDirective::escalate()),
        ]);

        return OnboardingStep::fromBlueprint('step-1', $blueprint, new \DateTimeImmutable());
    }
}
