<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Service;

use App\Onboarding\Domain\Model\OnboardingStep;
use App\Onboarding\Domain\State\OnboardingStepStatus;

/**
 * Interfejs handlera cyklu życia Action.
 *
 * Różne typy Action wymagają różnych side-effectów przy zmianie stanu.
 * KYC wymaga requestu do vendora. Podpisanie umowy — DocuSign.
 * Provisioning — API infrastruktury.
 *
 * supports() mówi: "obsługuję TEN typ akcji w TYM przejściu stanowym".
 * handle() wykonuje side-effect.
 */
interface ActionLifecycleHandler
{
    public function supports(OnboardingStep $step, OnboardingStepStatus $from, OnboardingStepStatus $to): bool;

    public function handle(OnboardingStep $step, OnboardingStepStatus $from, OnboardingStepStatus $to): void;
}
