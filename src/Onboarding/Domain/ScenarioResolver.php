<?php

declare(strict_types=1);

namespace Onboarding\Domain;

use SharedKernel\Activity\OutcomeBlueprint;
use SharedKernel\Activity\OutcomeDirective;
use SharedKernel\Activity\Policy\EscalateOnConflictPolicy;
use SharedKernel\Activity\Policy\TerminalWinsPolicy;
use SharedKernel\Activity\ScenarioBlueprint;
use SharedKernel\Activity\StageBlueprint;
use SharedKernel\Activity\StepBlueprint;

final class ScenarioResolver
{
    public function resolve(string $clientType): ScenarioBlueprint
    {
        return match ($clientType) {
            'enterprise' => $this->enterpriseScenario(),
            'sme' => $this->smeScenario(),
            default => throw new \InvalidArgumentException("Unknown client type: $clientType"),
        };
    }

    private function enterpriseScenario(): ScenarioBlueprint
    {
        return new ScenarioBlueprint(
            scenarioCode: 'enterprise_onboarding',
            description: 'Pełny onboarding Enterprise z KYC i AML',
            stages: [
                new StageBlueprint('kyc', 'Know Your Customer', [
                    new StepBlueprint('kyc_doc_verification', 'Weryfikacja dokumentów', [
                        new OutcomeBlueprint('accepted', 'Dokumenty zatwierdzone', OutcomeDirective::advance()),
                        new OutcomeBlueprint('needs_supplement', 'Brakujące dokumenty', OutcomeDirective::retry('kyc_doc_verification')),
                        new OutcomeBlueprint('rejected', 'Dokumenty odrzucone', OutcomeDirective::fail('KYC failed')),
                        new OutcomeBlueprint('suspicious', 'Podejrzenie oszustwa', OutcomeDirective::escalate()),
                    ]),
                ]),
                new StageBlueprint('contract', 'Podpisanie umowy', [
                    new StepBlueprint('contract_signing', 'Podpisanie umowy', [
                        new OutcomeBlueprint('signed', 'Umowa podpisana', OutcomeDirective::advance()),
                        new OutcomeBlueprint('declined', 'Umowa odrzucona', OutcomeDirective::fail('Contract declined')),
                    ]),
                ]),
                new StageBlueprint('provisioning', 'Provisioning środowiska', [
                    new StepBlueprint('env_provisioning', 'Provisioning środowiska klienta', [
                        new OutcomeBlueprint('provisioned', 'Środowisko gotowe', OutcomeDirective::advance()),
                        new OutcomeBlueprint('provisioning_failed', 'Błąd provisioningu', OutcomeDirective::retry('env_provisioning')),
                    ]),
                ]),
                new StageBlueprint('activation', 'Aktywacja konta', [
                    new StepBlueprint('account_activation', 'Aktywacja konta klienta', [
                        new OutcomeBlueprint('activated', 'Konto aktywne', OutcomeDirective::complete()),
                    ]),
                ]),
            ],
            conflictPolicy: new EscalateOnConflictPolicy(),
        );
    }

    private function smeScenario(): ScenarioBlueprint
    {
        return new ScenarioBlueprint(
            scenarioCode: 'sme_onboarding',
            description: 'Uproszczony onboarding SME',
            stages: [
                new StageBlueprint('kyc', 'Automatyczny KYC', [
                    new StepBlueprint('auto_kyc', 'Automatyczna weryfikacja', [
                        new OutcomeBlueprint('accepted', 'KYC OK', OutcomeDirective::advance()),
                        new OutcomeBlueprint('rejected', 'KYC failed', OutcomeDirective::fail('Auto KYC failed')),
                    ]),
                ]),
                new StageBlueprint('activation', 'Aktywacja', [
                    new StepBlueprint('account_activation', 'Aktywacja konta', [
                        new OutcomeBlueprint('activated', 'Konto aktywne', OutcomeDirective::complete()),
                    ]),
                ]),
            ],
            conflictPolicy: new TerminalWinsPolicy(),
        );
    }
}
