<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Onboarding;

use CrmArchetype\Archetype\ActionState;
use CrmArchetype\Archetype\PartySignature;
use CrmArchetype\Lifecycle\PendingCallback;
use CrmArchetype\Lifecycle\PendingCallbackRepository;
use CrmArchetype\Onboarding\Application\Command\RecordExternalOutcomeCommand;
use CrmArchetype\Onboarding\Application\Command\RecordExternalOutcomeHandler;
use CrmArchetype\Onboarding\OnboardingCase;
use CrmArchetype\Onboarding\OnboardingCaseRepository;
use CrmArchetype\Onboarding\OnboardingPhase;
use CrmArchetype\Onboarding\OnboardingStep;
use CrmArchetype\Onboarding\StepBlueprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecordExternalOutcomeHandlerTest extends TestCase
{
    private InMemoryPendingCallbackRepository $callbackRepo;
    private InMemoryOnboardingCaseRepository $caseRepo;
    private RecordExternalOutcomeHandler $handler;

    protected function setUp(): void
    {
        $this->callbackRepo = new InMemoryPendingCallbackRepository();
        $this->caseRepo = new InMemoryOnboardingCaseRepository();
        $this->handler = new RecordExternalOutcomeHandler($this->callbackRepo, $this->caseRepo);
    }

    #[Test]
    public function kyc_verified_completes_step_with_vendor_as_approver(): void
    {
        [$step, ] = $this->setupCaseWithPendingCallback('kyc_verify', 'KYC', 'Zaakceptowany', 'DoUzupelnienia', 'Odrzucony');

        ($this->handler)(new RecordExternalOutcomeCommand(
            actionId: 'kyc_verify',
            outcomeDescription: 'Zaakceptowane',
            outcomeReason: 'All documents valid',
            externalReference: 'kyc-ref-123',
        ));

        self::assertSame(ActionState::Completed, $step->state());
        self::assertCount(1, $step->actualOutcomes());
        self::assertSame('Zaakceptowane', $step->actualOutcomes()[0]->description);
        self::assertSame('All documents valid', $step->actualOutcomes()[0]->reason);

        // Vendor recorded as outcome approver (PartySignature)
        $approver = $step->actualOutcomes()[0]->approvers[0];
        self::assertSame('vendor:kyc_provider', $approver->partyId);
        self::assertSame('external_verifier', $approver->role);

        // Callback marked as resolved
        $callback = $this->callbackRepo->findByActionId('kyc_verify');
        self::assertTrue($callback->isResolved());
    }

    #[Test]
    public function kyc_needs_supplement_keeps_step_in_progress(): void
    {
        [$step, ] = $this->setupCaseWithPendingCallback('kyc_verify', 'KYC', 'Zaakceptowany', 'DoUzupelnienia', 'Odrzucony');

        ($this->handler)(new RecordExternalOutcomeCommand(
            actionId: 'kyc_verify',
            outcomeDescription: 'DoUzupelnienia',
            outcomeReason: 'Missing KRS document',
        ));

        // Step stays InProgress — not completed
        self::assertSame(ActionState::InProgress, $step->state());

        // Outcome recorded
        self::assertCount(1, $step->actualOutcomes());
        self::assertSame('DoUzupelnienia', $step->actualOutcomes()[0]->description);
        self::assertTrue($step->requiresSupplement());

        // Callback resolved
        self::assertTrue($this->callbackRepo->findByActionId('kyc_verify')->isResolved());
    }

    #[Test]
    public function kyc_rejected_fails_step(): void
    {
        [$step, ] = $this->setupCaseWithPendingCallback('kyc_verify', 'KYC', 'Zaakceptowany', 'DoUzupelnienia', 'Odrzucony');

        ($this->handler)(new RecordExternalOutcomeCommand(
            actionId: 'kyc_verify',
            outcomeDescription: 'Odrzucone',
            outcomeReason: 'Fraudulent documents',
        ));

        self::assertSame(ActionState::Failed, $step->state());
        self::assertCount(1, $step->actualOutcomes());
        self::assertSame('Odrzucone', $step->actualOutcomes()[0]->description);
        self::assertTrue($this->callbackRepo->findByActionId('kyc_verify')->isResolved());
    }

    #[Test]
    public function contract_signed_completes_step(): void
    {
        [$step, ] = $this->setupCaseWithPendingCallback('contract_sign', 'Umowa', 'Podpisano', 'Odrzucone');

        ($this->handler)(new RecordExternalOutcomeCommand(
            actionId: 'contract_sign',
            outcomeDescription: 'Podpisano',
            externalReference: 'envelope-456',
        ));

        self::assertSame(ActionState::Completed, $step->state());
        self::assertSame('Podpisano', $step->actualOutcomes()[0]->description);
    }

    #[Test]
    public function throws_when_no_pending_callback_found(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No pending callback for action');

        ($this->handler)(new RecordExternalOutcomeCommand(
            actionId: 'nonexistent',
            outcomeDescription: 'Zaakceptowane',
        ));
    }

    #[Test]
    public function throws_when_case_not_found(): void
    {
        $this->callbackRepo->store(new PendingCallback(
            actionId: 'orphan-step',
            caseId: 'nonexistent-case',
            stageCode: 'KYC',
            stepCode: 'orphan-step',
            externalReference: 'ref',
            vendor: 'kyc_provider',
            expectedCallbackBy: new \DateTimeImmutable('+1 hour'),
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Onboarding case "nonexistent-case" not found');

        ($this->handler)(new RecordExternalOutcomeCommand(
            actionId: 'orphan-step',
            outcomeDescription: 'Zaakceptowane',
        ));
    }

    #[Test]
    public function throws_when_step_not_found_in_case(): void
    {
        $case = new OnboardingCase('Test', 'desc', 'user-1');
        $phase = new OnboardingPhase('KYC');
        $case->addPhase($phase);
        $this->caseRepo->save('case-1', $case);

        $this->callbackRepo->store(new PendingCallback(
            actionId: 'missing-step',
            caseId: 'case-1',
            stageCode: 'KYC',
            stepCode: 'missing-step',
            externalReference: 'ref',
            vendor: 'kyc_provider',
            expectedCallbackBy: new \DateTimeImmutable('+1 hour'),
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Step "missing-step" not found in stage "KYC"');

        ($this->handler)(new RecordExternalOutcomeCommand(
            actionId: 'missing-step',
            outcomeDescription: 'Zaakceptowane',
        ));
    }

    /**
     * @return array{OnboardingStep, OnboardingCase}
     */
    private function setupCaseWithPendingCallback(
        string $stepCode,
        string $stageName,
        string ...$possibleOutcomes,
    ): array {
        $step = OnboardingStep::fromBlueprint(
            new StepBlueprint($stepCode, "Step: {$stepCode}", array_values($possibleOutcomes)),
            new PartySignature('op-1', 'compliance'),
        );

        // Bring step to InProgress (simulating lifecycle dispatch)
        $step->submit();
        $step->start();

        $phase = new OnboardingPhase($stageName, "Phase: {$stageName}");
        $phase->addStep($step);

        $case = new OnboardingCase('Test Case', 'Onboarding test', 'sales-1');
        $case->addPhase($phase);

        $this->caseRepo->save('case-1', $case);

        $this->callbackRepo->store(new PendingCallback(
            actionId: $stepCode,
            caseId: 'case-1',
            stageCode: $stageName,
            stepCode: $stepCode,
            externalReference: 'ext-ref-123',
            vendor: 'kyc_provider',
            expectedCallbackBy: new \DateTimeImmutable('+24 hours'),
        ));

        return [$step, $case];
    }
}

// --- In-memory test doubles ---

/**
 * @internal
 */
final class InMemoryPendingCallbackRepository implements PendingCallbackRepository
{
    /** @var array<string, PendingCallback> */
    private array $callbacks = [];

    public function store(PendingCallback $callback): void
    {
        $this->callbacks[$callback->actionId()] = $callback;
    }

    public function findByActionId(string $actionId): ?PendingCallback
    {
        return $this->callbacks[$actionId] ?? null;
    }

    /** @return PendingCallback[] */
    public function findOverdue(): array
    {
        return array_filter($this->callbacks, fn(PendingCallback $cb) => $cb->isOverdue());
    }

    public function markResolved(string $actionId): void
    {
        $this->callbacks[$actionId]?->markResolved();
    }
}

/**
 * @internal
 */
final class InMemoryOnboardingCaseRepository implements OnboardingCaseRepository
{
    /** @var array<string, OnboardingCase> */
    private array $cases = [];

    public function save(string $id, OnboardingCase $case): void
    {
        $this->cases[$id] = $case;
    }

    public function findById(string $caseId): ?OnboardingCase
    {
        return $this->cases[$caseId] ?? null;
    }
}
