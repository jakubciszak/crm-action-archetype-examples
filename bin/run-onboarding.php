<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Onboarding\Domain\OnboardingCase;
use Onboarding\Domain\ScenarioResolver;
use Onboarding\Infrastructure\InMemoryOnboardingCaseRepository;
use Onboarding\Infrastructure\InMemoryPendingCallbackRepository;
use Onboarding\Infrastructure\Lifecycle\ContractSigningHandler;
use Onboarding\Infrastructure\Lifecycle\KycVendorHandler;
use Onboarding\Infrastructure\Vendor\FakeKycVendorClient;
use SharedKernel\Activity\ActionState;
use SharedKernel\Activity\Lifecycle\ActionLifecycleDispatcher;
use SharedKernel\Activity\Outcome;
use SharedKernel\Activity\PartySignature;

echo "=== ONBOARDING B2B: Enterprise ===\n\n";

// --- Wiring ---
$scenarioResolver = new ScenarioResolver();
$caseRepo = new InMemoryOnboardingCaseRepository();
$callbackRepo = new InMemoryPendingCallbackRepository();
$kycClient = new FakeKycVendorClient();
$lifecycleDispatcher = new ActionLifecycleDispatcher([
    new KycVendorHandler($kycClient, $callbackRepo),
    new ContractSigningHandler($callbackRepo),
]);

// 1. Start
$scenario = $scenarioResolver->resolve('enterprise');
$case = OnboardingCase::fromScenario('CASE-001', 'Acme Corp', 'enterprise', $scenario);
$caseRepo->save($case);

echo "1. Start: OnboardingCase dla \"Acme Corp\" (profil: enterprise)\n";
echo "   Scenariusz: enterprise_onboarding (4 fazy, policy: EscalateOnConflictPolicy)\n\n";

// 2. KYC
echo "2. KYC: start step 'kyc_doc_verification'\n";
$case->startStep('kyc', 'kyc_doc_verification');
echo "   → Pending → InProgress\n";

$stage = $case->currentStage();
$step = $stage->findStep('kyc_doc_verification');
$lifecycleResult = $lifecycleDispatcher->dispatch(
    $step, 'kyc_doc_verification', ActionState::Pending, ActionState::InProgress
);
echo "   → KycVendorHandler dispatched → AwaitingCallback (verificationId: {$lifecycleResult->externalReference})\n";

// Symulacja callback
echo "   → [symulacja] Vendor callback: 'accepted'\n";
$vendorSignature = new PartySignature('kyc_vendor', 'vendor', new \DateTimeImmutable());
$outcome = new Outcome('accepted', 'Dokumenty zatwierdzone', approver: $vendorSignature);
$directives = $case->completeStep('kyc', 'kyc_doc_verification', $outcome, $vendorSignature);
echo "   → Outcome recorded, directive: advance()\n";
echo "   → Stage KYC completed, advancing to 'contract'\n\n";

// 3. Contract
echo "3. Contract: start step 'contract_signing'\n";
$case->startStep('contract', 'contract_signing');
echo "   → Pending → InProgress\n";

$stage = $case->currentStage();
$step = $stage->findStep('contract_signing');
$lifecycleResult = $lifecycleDispatcher->dispatch(
    $step, 'contract_signing', ActionState::Pending, ActionState::InProgress
);
echo "   → ContractSigningHandler dispatched → AwaitingCallback (envelopeId: {$lifecycleResult->externalReference})\n";

echo "   → [symulacja] Vendor callback: 'signed'\n";
$docusignSignature = new PartySignature('docusign', 'vendor', new \DateTimeImmutable());
$outcome = new Outcome('signed', 'Umowa podpisana', approver: $docusignSignature);
$directives = $case->completeStep('contract', 'contract_signing', $outcome, $docusignSignature);
echo "   → Outcome recorded, directive: advance()\n\n";

// 4. Provisioning
echo "4. Provisioning: start step 'env_provisioning'\n";
$case->startStep('provisioning', 'env_provisioning');
$outcome = new Outcome('provisioned', 'Środowisko gotowe');
$directives = $case->completeStep('provisioning', 'env_provisioning', $outcome);
echo "   → Pending → InProgress → Completed (sync)\n";
echo "   → directive: advance()\n\n";

// 5. Activation
echo "5. Activation: start step 'account_activation'\n";
$case->startStep('activation', 'account_activation');
$outcome = new Outcome('activated', 'Konto aktywne');
$directives = $case->completeStep('activation', 'account_activation', $outcome);
echo "   → Completed, directive: complete()\n";

$events = $case->releaseEvents();
foreach ($events as $event) {
    echo "   → " . (new \ReflectionClass($event))->getShortName() . " event!\n";
}

echo "\n=== DONE ===\n";
