<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Service;

use App\Onboarding\Domain\Model\CustomerType;
use App\Onboarding\Domain\Model\OnboardingCase;
use App\Onboarding\Domain\Model\OnboardingStage;
use App\Onboarding\Domain\Model\OnboardingStep;
use App\Onboarding\Domain\Model\OnboardingTrigger;
use App\Onboarding\Domain\Model\StageType;
use App\Onboarding\Domain\Model\StepResult;

/**
 * Buduje scenariusz onboardingu na podstawie profilu klienta.
 *
 * Enterprise: KYC (3 kroki + AML) → Umowa → Setup → Szkolenie
 * SME: KYC automatyczne (1 krok) → Umowa → Setup
 */
final class OnboardingScenarioBuilder
{
    private int $idCounter = 0;

    public function buildForCase(OnboardingCase $case): void
    {
        match ($case->customerType()) {
            CustomerType::Enterprise => $this->buildEnterpriseScenario($case),
            CustomerType::SME => $this->buildSmeScenario($case),
        };
    }

    private function buildEnterpriseScenario(OnboardingCase $case): void
    {
        $kyc = $this->createStageWithSteps(
            StageType::KYC,
            0,
            ['Weryfikacja dokumentów', 'Weryfikacja beneficjentów', 'AML Screening'],
        );
        $contract = $this->createStageWithSteps(
            StageType::Contract,
            1,
            ['Przygotowanie umowy', 'Podpisanie umowy'],
        );
        $setup = $this->createStageWithSteps(
            StageType::Setup,
            2,
            ['Provisioning konta', 'Konfiguracja integracji'],
        );
        $training = $this->createStageWithSteps(
            StageType::Training,
            3,
            ['Szkolenie administratora', 'Szkolenie użytkowników'],
        );

        $case->addStage($kyc);
        $case->addStage($contract);
        $case->addStage($setup);
        $case->addStage($training);
    }

    private function buildSmeScenario(OnboardingCase $case): void
    {
        $kyc = $this->createStageWithSteps(
            StageType::KYC,
            0,
            ['Automatyczna weryfikacja KYC'],
        );
        $contract = $this->createStageWithSteps(
            StageType::Contract,
            1,
            ['Akceptacja regulaminu online'],
        );
        $setup = $this->createStageWithSteps(
            StageType::Setup,
            2,
            ['Provisioning konta'],
        );

        $case->addStage($kyc);
        $case->addStage($contract);
        $case->addStage($setup);
    }

    private function createStageWithSteps(StageType $type, int $order, array $stepNames): OnboardingStage
    {
        $stage = new OnboardingStage($this->nextId(), $type, $order);

        $trigger = OnboardingTrigger::systemEvent($this->nextId(), 'stage_initiated');

        foreach ($stepNames as $stepName) {
            $step = OnboardingStep::fromBlueprint(
                $this->nextId(),
                $stepName,
                StepResult::standardPossibleOutcomes(),
            );
            $trigger->addAction($step);
        }

        $stage->addCommunication($trigger);

        return $stage;
    }

    private function nextId(): string
    {
        return 'id-' . ++$this->idCounter;
    }
}
