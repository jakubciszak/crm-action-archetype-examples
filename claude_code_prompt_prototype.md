# Prototyp: Archetyp CRM Activity — dwie domeny, wspólny rdzeń

## Cel

Zbuduj działający prototyp systemu opartego na archetypie CRM Activity (Arlow & Neustadt, "Enterprise Patterns and MDA", rozdział 6). System ma demonstrować, że **jeden głęboki model** obsługuje dwie zupełnie różne domeny:

1. **Onboarding B2B** — sekwencyjny, wieloetapowy, z KYC i compliance, z async vendorami
2. **Program lojalnościowy** — event-driven, z naliczaniem punktów, nagrodami i chargeback

Prototyp ma być **uruchamialny** — z testami, CLI runneram i dwoma scenariuszami end-to-end.

## Stack technologiczny

- **PHP 8.4** — enum, readonly, named arguments, `array_any()`, `array_all()`, match expressions
- **Bez frameworka** — czysty PHP, bez Symfony/Laravel. In-memory implementacje repozytoriów.
- **PHPUnit 11** — testy jednostkowe i integracyjne
- **Composer** z PSR-4 autoloading
- **CLI runner** — dwa skrypty demonstracyjne: `bin/run-onboarding.php` i `bin/run-loyalty.php`

## Architektura źródłowa — archetyp CRM Activity

Archetyp CRM Activity definiuje hierarchię:

```
CustomerCommunicationManager
  └── CustomerServiceCase          — kontener procesu (priorytet, cykl życia)
       └── CommunicationThread     — faza / etap / wątek tematyczny
            └── Communication      — trigger / zdarzenie inicjujące
                 └── Action        — krok procesu z maszyną stanów
                      ├── possibleOutcomes: Outcome[]   — znane z góry ścieżki
                      ├── actualOutcomes: Outcome[]     — co się naprawdę stało (runtime)
                      └── PartySignature                — kto zainicjował, kto zatwierdził
```

**Nie implementujemy całości.** Bierzemy klocki, które potrzebujemy:
- Communication → Action → Outcome (triada)
- PartySignature (audit trail)
- CustomerServiceCase + CommunicationThread (kontenery)
- Status: pending → open → closed (archetyp), rozbudowany do pełnej maszyny stanów

---

## Struktura katalogów

```
src/
├── SharedKernel/
│   └── Activity/
│       ├── Action.php                          # Abstrakcyjna klasa bazowa
│       ├── ActionState.php                     # Enum: Draft, Pending, InProgress, AwaitingApproval, Completed, Failed, OnHold, Escalated
│       ├── Outcome.php                         # Value object
│       ├── OutcomeBlueprint.php                # Deklaratywna definicja outcome + dyrektywa
│       ├── OutcomeDirective.php                # Value object: advance(), retry(), spawnStep(), fail(), escalate(), hold()
│       ├── OutcomeDirectiveType.php            # Enum
│       ├── OutcomeDirectiveSet.php             # Zbiera dyrektywy z wielu outcomes, deleguje do polityki
│       ├── DirectiveConflictPolicy.php         # Interface
│       ├── Policy/
│       │   ├── TerminalWinsPolicy.php          # fail/escalate wygrywa nad advance/retry
│       │   └── EscalateOnConflictPolicy.php    # jakikolwiek konflikt = eskaluj do człowieka
│       ├── PartySignature.php                  # Value object: initiator, approvers
│       ├── StepBlueprint.php                   # Deklaratywna definicja kroku
│       ├── StageBlueprint.php                  # Deklaratywna definicja fazy
│       ├── ScenarioBlueprint.php               # Cały scenariusz: stage'e + policy
│       ├── Lifecycle/
│       │   ├── ActionLifecycleHandler.php       # Interface: supports() + handle()
│       │   ├── ActionLifecycleResult.php        # VO: completed() | awaitingCallback() | failed()
│       │   ├── ActionLifecycleDispatcher.php    # Iteruje handlery, odpala matching
│       │   ├── PendingCallback.php              # Entity: czekamy na vendora
│       │   └── PendingCallbackRepository.php    # Interface
│       └── Event/
│           ├── DomainEvent.php                  # Interface
│           ├── StepCompleted.php
│           ├── StepFailed.php
│           ├── StageAdvanced.php
│           ├── ProcessCompleted.php
│           └── OutcomeDirectiveDispatched.php
│
├── Onboarding/
│   ├── Domain/
│   │   ├── OnboardingCase.php                  # Aggregate root: CSCase → OnboardingCase
│   │   ├── OnboardingStage.php                 # CommunicationThread → stage
│   │   ├── OnboardingStep.php                  # Action → step (DZIEDZICZY po Action)
│   │   ├── OnboardingState.php                 # Enum: Draft, Pending, InProgress, AwaitingApproval, Completed, OnHold, Failed, Escalated
│   │   ├── StepOutcome.php                     # Outcome specyficzny dla onboardingu
│   │   └── ScenarioResolver.php                # Dobiera scenariusz per profil klienta
│   ├── Application/
│   │   ├── StartOnboardingCommand.php
│   │   ├── StartOnboardingHandler.php
│   │   ├── CompleteStepCommand.php
│   │   ├── CompleteStepHandler.php
│   │   ├── RecordExternalOutcomeCommand.php
│   │   └── RecordExternalOutcomeHandler.php
│   └── Infrastructure/
│       ├── InMemoryOnboardingCaseRepository.php
│       ├── InMemoryPendingCallbackRepository.php
│       ├── Lifecycle/
│       │   ├── KycVendorHandler.php             # supports('kyc_verification', Pending→InProgress)
│       │   └── ContractSigningHandler.php       # supports('contract_signing', Pending→InProgress)
│       └── Vendor/
│           ├── KycVendorClient.php              # Interface
│           └── FakeKycVendorClient.php          # In-memory fake dla prototypu
│
├── Loyalty/
│   ├── Domain/
│   │   ├── LoyaltyCampaign.php                 # CSCase → kampania
│   │   ├── ActivityStream.php                   # CommunicationThread → typ aktywności
│   │   ├── IncentiveAction.php                 # Action → NIE dziedziczy (własna maszyna 6 stanów)
│   │   ├── IncentiveActionState.php            # Enum: Received, Evaluating, AwaitingSettlement, Settled, Rejected, Reversed
│   │   ├── IncentiveDecision.php               # Outcome z efektami: journalEntries, rewardGrants, events
│   │   ├── JournalEntry.php                    # Zapis księgowy punktów
│   │   ├── RewardGrant.php                     # Przyznana nagroda
│   │   └── IncentiveRule.php                   # Interface: supports(ActionType) + evaluate()
│   ├── Application/
│   │   ├── RecordActionCommand.php
│   │   ├── RecordActionHandler.php
│   │   ├── ReverseActionCommand.php
│   │   └── ReverseActionHandler.php
│   └── Infrastructure/
│       ├── InMemoryLoyaltyCampaignRepository.php
│       └── Rules/
│           ├── OrderPointsRule.php              # Zakup → punkty per kwota
│           └── ReferralBonusRule.php            # Polecenie → bonus punktowy
│
tests/
├── SharedKernel/
│   ├── OutcomeDirectiveSetTest.php
│   ├── TerminalWinsPolicyTest.php
│   ├── EscalateOnConflictPolicyTest.php
│   └── ActionLifecycleDispatcherTest.php
├── Onboarding/
│   ├── OnboardingCaseTest.php                  # Pełen flow: start → KYC → umowa → provisioning → aktywacja
│   ├── OnboardingStepTest.php                  # State transitions, requiresSupplement()
│   └── ScenarioResolverTest.php
└── Loyalty/
    ├── IncentiveActionTest.php                 # settle(), reverse(), state transitions
    ├── LoyaltyCampaignTest.php                # Pełen flow: zakup → punkty → nagroda
    └── IncentiveRuleTest.php

bin/
├── run-onboarding.php                         # Demo: Enterprise onboarding z 4 fazami
└── run-loyalty.php                            # Demo: Kampania z zakupami i poleceniami
```

---

## Szczegóły implementacji — SharedKernel

### ActionState (enum)

```php
enum ActionState: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case OnHold = 'on_hold';
    case Escalated = 'escalated';

    /** @return ActionState[] dozwolone przejścia z tego stanu */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending],
            self::Pending => [self::InProgress, self::OnHold],
            self::InProgress => [self::AwaitingApproval, self::Completed, self::Failed, self::Escalated],
            self::AwaitingApproval => [self::Completed, self::Failed],
            self::Failed => [self::InProgress],  // retry
            self::OnHold => [self::Pending],      // resume
            self::Completed => [],
            self::Escalated => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Escalated => true,
            default => false,
        };
    }
}
```

### Action (abstrakcyjna klasa bazowa)

```php
abstract class Action
{
    private ActionState $state = ActionState::Draft;
    private array $possibleOutcomes = [];   // OutcomeBlueprint[]
    private array $actualOutcomes = [];      // Outcome[]
    private ?PartySignature $initiator = null;
    private array $approvers = [];           // PartySignature[]
    private array $domainEvents = [];

    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly \DateTimeImmutable $createdAt,
    ) {}

    /** Ładuje possibleOutcomes z blueprintu */
    public static function fromBlueprint(string $id, StepBlueprint $blueprint, \DateTimeImmutable $now): static
    {
        $action = new static($id, $blueprint->stepCode, $now);
        $action->possibleOutcomes = $blueprint->possibleOutcomes;
        return $action;
    }

    public function transitionTo(ActionState $target): void
    {
        if (!$this->state->canTransitionTo($target)) {
            throw new \DomainException("Cannot transition from {$this->state->value} to {$target->value}");
        }
        $previousState = $this->state;
        $this->state = $target;
        // Zwraca previous state dla lifecycle handlera
    }

    public function recordOutcome(Outcome $outcome): void
    {
        // Walidacja: outcome musi być w possibleOutcomes (match po code)
        $blueprint = $this->findOutcomeBlueprint($outcome->code);
        if ($blueprint === null) {
            throw new \DomainException("Outcome '{$outcome->code}' is not in possibleOutcomes");
        }
        $this->actualOutcomes[] = $outcome;
    }

    public function complete(Outcome ...$outcomes): OutcomeDirectiveSet
    {
        foreach ($outcomes as $o) {
            $this->recordOutcome($o);
        }
        $this->transitionTo(ActionState::Completed);

        // Zbierz dyrektywy z blueprintów matchujących actualOutcomes
        $directives = [];
        foreach ($outcomes as $o) {
            $bp = $this->findOutcomeBlueprint($o->code);
            $directives[] = $bp->directive;
        }
        return new OutcomeDirectiveSet($directives);
    }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // gettery: state(), type(), id(), possibleOutcomes(), actualOutcomes(), initiator()
}
```

### Outcome (value object)

```php
final readonly class Outcome
{
    public function __construct(
        public string $code,
        public string $description,
        public ?string $reason = null,
        public ?PartySignature $approver = null,
        public \DateTimeImmutable $recordedAt = new \DateTimeImmutable(),
    ) {}
}
```

### OutcomeBlueprint (deklaratywna definicja outcome)

```php
final readonly class OutcomeBlueprint
{
    public function __construct(
        public string $code,
        public string $description,
        public OutcomeDirective $directive,
    ) {}
}
```

### OutcomeDirective (value object)

```php
final readonly class OutcomeDirective
{
    private function __construct(
        public OutcomeDirectiveType $type,
        public array $params = [],
    ) {}

    public static function advance(): self { return new self(OutcomeDirectiveType::AdvanceStage); }
    public static function retry(string $stepCode): self { return new self(OutcomeDirectiveType::RetryStep, ['stepCode' => $stepCode]); }
    public static function spawnStep(string $stepCode, string $stageCode): self { return new self(OutcomeDirectiveType::SpawnStep, ['stepCode' => $stepCode, 'stageCode' => $stageCode]); }
    public static function complete(): self { return new self(OutcomeDirectiveType::CompleteProcess); }
    public static function fail(string $reason): self { return new self(OutcomeDirectiveType::FailProcess, ['reason' => $reason]); }
    public static function escalate(): self { return new self(OutcomeDirectiveType::Escalate); }
    public static function hold(string $reason): self { return new self(OutcomeDirectiveType::Hold, ['reason' => $reason]); }
}

enum OutcomeDirectiveType: string
{
    case AdvanceStage = 'advance_stage';
    case RetryStep = 'retry_step';
    case SpawnStep = 'spawn_step';
    case CompleteProcess = 'complete_process';
    case FailProcess = 'fail_process';
    case Escalate = 'escalate';
    case Hold = 'hold';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::FailProcess, self::Escalate, self::Hold => true,
            default => false,
        };
    }

    /** SpawnStep może współistnieć z innymi nieterminalnymi */
    public function isComposable(): bool
    {
        return $this === self::SpawnStep;
    }

    public function priority(): int
    {
        return match ($this) {
            self::FailProcess => 1,
            self::Escalate => 2,
            self::Hold => 3,
            self::RetryStep => 4,
            self::AdvanceStage => 5,
            self::SpawnStep => 6,
            self::CompleteProcess => 7,
        };
    }
}
```

### OutcomeDirectiveSet + DirectiveConflictPolicy

```php
final readonly class OutcomeDirectiveSet
{
    /** @param OutcomeDirective[] $directives */
    public function __construct(
        private array $directives,
    ) {}

    /** @return OutcomeDirective[] rozwiązane dyrektywy do wykonania */
    public function resolve(DirectiveConflictPolicy $policy): array
    {
        return $policy->resolve($this->directives);
    }
}

interface DirectiveConflictPolicy
{
    /** @param OutcomeDirective[] $directives @return OutcomeDirective[] */
    public function resolve(array $directives): array;
}
```

### TerminalWinsPolicy

```php
final readonly class TerminalWinsPolicy implements DirectiveConflictPolicy
{
    public function resolve(array $directives): array
    {
        $terminals = array_filter($directives, fn(OutcomeDirective $d) => $d->type->isTerminal());
        $spawns = array_filter($directives, fn(OutcomeDirective $d) => $d->type->isComposable());

        if ($terminals !== []) {
            // Najwyższy priorytet terminal + wszystkie spawny
            usort($terminals, fn($a, $b) => $a->type->priority() <=> $b->type->priority());
            return [$terminals[0], ...$spawns];
        }

        // Brak terminali — sprawdź konflikty nieterminalne
        $nonSpawn = array_filter($directives, fn(OutcomeDirective $d) => !$d->type->isComposable());
        if (count($nonSpawn) > 1) {
            // advance + retry = konflikt → wyższy priorytet wygrywa
            usort($nonSpawn, fn($a, $b) => $a->type->priority() <=> $b->type->priority());
            return [$nonSpawn[0], ...$spawns];
        }

        return $directives;
    }
}
```

### EscalateOnConflictPolicy

```php
final readonly class EscalateOnConflictPolicy implements DirectiveConflictPolicy
{
    public function resolve(array $directives): array
    {
        $nonComposable = array_filter($directives, fn(OutcomeDirective $d) => !$d->type->isComposable());

        if (count($nonComposable) > 1) {
            return [OutcomeDirective::escalate()];
        }

        return $directives;
    }
}
```

### ActionLifecycleHandler + Dispatcher

```php
interface ActionLifecycleHandler
{
    public function supports(string $actionType, ActionState $from, ActionState $to): bool;
    public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult;
}

final readonly class ActionLifecycleResult
{
    private function __construct(
        public ActionLifecycleStatus $status,
        public ?string $externalReference = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}

    public static function completed(array $metadata = []): self { return new self(ActionLifecycleStatus::Completed, metadata: $metadata); }
    public static function awaitingCallback(string $externalReference): self { return new self(ActionLifecycleStatus::AwaitingCallback, externalReference: $externalReference); }
    public static function failed(string $message): self { return new self(ActionLifecycleStatus::Failed, message: $message); }
}

enum ActionLifecycleStatus { case Completed; case AwaitingCallback; case Failed; }

final class ActionLifecycleDispatcher
{
    /** @param ActionLifecycleHandler[] $handlers */
    public function __construct(private iterable $handlers) {}

    public function dispatch(Action $action, string $actionType, ActionState $from, ActionState $to): ActionLifecycleResult
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($actionType, $from, $to)) {
                return $handler->handle($action, $from, $to);
            }
        }
        return ActionLifecycleResult::completed(); // brak handlera = OK
    }
}
```

### StepBlueprint, StageBlueprint, ScenarioBlueprint

```php
final readonly class StepBlueprint
{
    /** @param OutcomeBlueprint[] $possibleOutcomes */
    public function __construct(
        public string $stepCode,
        public string $description,
        public array $possibleOutcomes,
    ) {}
}

final readonly class StageBlueprint
{
    /** @param StepBlueprint[] $steps */
    public function __construct(
        public string $stageCode,
        public string $description,
        public array $steps,
    ) {}
}

final readonly class ScenarioBlueprint
{
    /** @param StageBlueprint[] $stages */
    public function __construct(
        public string $scenarioCode,
        public string $description,
        public array $stages,
        public DirectiveConflictPolicy $conflictPolicy,
    ) {}
}
```

---

## Szczegóły implementacji — Onboarding B2B

### OnboardingStep (dziedziczy po Action)

```php
final class OnboardingStep extends Action
{
    public function requiresSupplement(): bool
    {
        return array_any(
            $this->actualOutcomes(),
            fn(Outcome $o) => $o->code === 'needs_supplement'
        );
    }

    public function isRejected(): bool
    {
        return array_any(
            $this->actualOutcomes(),
            fn(Outcome $o) => $o->code === 'rejected'
        );
    }
}
```

### OnboardingCase (aggregate root)

Zarządza stage'ami i stepami. Metody:
- `startStep(stageCode, stepCode)` — transitionTo(InProgress)
- `completeStep(stageCode, stepCode, Outcome..., ?PartySignature)` — recordOutcome, resolve directives
- `advanceStage(stageCode)` — przejście do następnego stage
- `currentStage(): OnboardingStage`
- `isComplete(): bool`

Scenariusz ładowany z `ScenarioBlueprint` — ScenarioResolver wybiera per profil klienta.

### ScenarioResolver

```php
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
            conflictPolicy: new EscalateOnConflictPolicy(), // Enterprise = bezpieczeństwo
        );
    }

    private function smeScenario(): ScenarioBlueprint
    {
        // Uproszczony scenariusz: KYC automatyczny + provisioning
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
            conflictPolicy: new TerminalWinsPolicy(), // SME = szybkość
        );
    }
}
```

### KycVendorHandler (lifecycle — w Infrastructure!)

```php
final readonly class KycVendorHandler implements ActionLifecycleHandler
{
    public function __construct(
        private KycVendorClient $client,
        private PendingCallbackRepository $pendingCallbacks,
    ) {}

    public function supports(string $actionType, ActionState $from, ActionState $to): bool
    {
        return $actionType === 'kyc_doc_verification'
            && $from === ActionState::Pending
            && $to === ActionState::InProgress;
    }

    public function handle(Action $action, ActionState $from, ActionState $to): ActionLifecycleResult
    {
        $verificationId = $this->client->submitVerification($action->id());

        $this->pendingCallbacks->store(new PendingCallback(
            actionId: $action->id(),
            externalReference: $verificationId,
            vendor: 'kyc_vendor',
            expectedCallbackBy: new \DateTimeImmutable('+24 hours'),
        ));

        return ActionLifecycleResult::awaitingCallback($verificationId);
    }
}
```

---

## Szczegóły implementacji — Program Lojalnościowy

### IncentiveAction (NIE dziedziczy po Action — inna maszyna stanów)

```php
final class IncentiveAction
{
    private IncentiveActionState $state = IncentiveActionState::Received;
    private ?IncentiveDecision $decision = null;
    private array $domainEvents = [];

    public function __construct(
        private readonly string $id,
        private readonly string $actionType,
        private readonly array $payload,
        private readonly string $participantId,
        private readonly \DateTimeImmutable $occurredAt,
    ) {}

    public function evaluate(IncentiveRule ...$rules): void
    {
        $this->transitionTo(IncentiveActionState::Evaluating);

        foreach ($rules as $rule) {
            if ($rule->supports($this->actionType)) {
                $this->decision = $rule->evaluate($this);
                break;
            }
        }

        if ($this->decision === null) {
            $this->transitionTo(IncentiveActionState::Rejected);
            return;
        }

        $this->transitionTo(IncentiveActionState::AwaitingSettlement);
    }

    public function settle(): void
    {
        if ($this->decision === null) {
            throw new \DomainException('Cannot settle without decision');
        }
        $this->transitionTo(IncentiveActionState::Settled);
        // Emituj domain events per efekt
        foreach ($this->decision->journalEntries as $entry) {
            $this->domainEvents[] = new PointsGranted($this->participantId, $entry);
        }
        foreach ($this->decision->rewardGrants as $grant) {
            $this->domainEvents[] = new RewardGranted($this->participantId, $grant);
        }
    }

    public function reverse(string $reason): void
    {
        if ($this->state !== IncentiveActionState::Settled) {
            throw new \DomainException('Can only reverse settled actions');
        }
        $this->transitionTo(IncentiveActionState::Reversed);
        // Emituj odwrócenie
        foreach ($this->decision->journalEntries as $entry) {
            $this->domainEvents[] = new PointsDebited($this->participantId, $entry, $reason);
        }
    }

    // transitionTo() z guard na allowedTransitions w IncentiveActionState
}
```

### IncentiveActionState (enum — 6 stanów, NIE 8)

```php
enum IncentiveActionState: string
{
    case Received = 'received';
    case Evaluating = 'evaluating';
    case AwaitingSettlement = 'awaiting_settlement';
    case Settled = 'settled';
    case Rejected = 'rejected';
    case Reversed = 'reversed';  // stan SPOZA archetypu — głęboki model go dodaje

    public function allowedTransitions(): array { /* ... */ }
}
```

### IncentiveDecision (Outcome z efektami biznesowymi)

```php
final readonly class IncentiveDecision
{
    /** @param JournalEntry[] $journalEntries @param RewardGrant[] $rewardGrants */
    public function __construct(
        public array $journalEntries,
        public array $rewardGrants,
        public array $events = [],
    ) {}
}
```

### IncentiveRule (interface)

```php
interface IncentiveRule
{
    public function supports(string $actionType): bool;
    public function evaluate(IncentiveAction $action): IncentiveDecision;
}
```

### OrderPointsRule (1 punkt za 10 zł)

```php
final readonly class OrderPointsRule implements IncentiveRule
{
    public function supports(string $actionType): bool
    {
        return $actionType === 'order_placed';
    }

    public function evaluate(IncentiveAction $action): IncentiveDecision
    {
        $amount = $action->payload()['totalAmountCents'];
        $points = intdiv($amount, 1000); // 1 punkt za 10 zł

        return new IncentiveDecision(
            journalEntries: [new JournalEntry(points: $points, reason: "Order {$action->payload()['orderId']}")],
            rewardGrants: $points >= 100 ? [new RewardGrant('free_shipping', 'Darmowa dostawa')] : [],
        );
    }
}
```

---

## Scenariusze demonstracyjne (bin/)

### bin/run-onboarding.php

```
=== ONBOARDING B2B: Enterprise ===

1. Start: OnboardingCase dla "Acme Corp" (profil: enterprise)
   Scenariusz: enterprise_onboarding (4 fazy, policy: EscalateOnConflictPolicy)

2. KYC: start step 'kyc_doc_verification'
   → Pending → InProgress
   → KycVendorHandler dispatched → AwaitingCallback (verificationId: VRF-001)
   → [symulacja] Vendor callback: 'accepted'
   → Outcome recorded, directive: advance()
   → Stage KYC completed, advancing to 'contract'

3. Contract: start step 'contract_signing'
   → Pending → InProgress
   → ContractSigningHandler dispatched → AwaitingCallback (envelopeId: ENV-001)
   → [symulacja] Vendor callback: 'signed'
   → Outcome recorded, directive: advance()

4. Provisioning: start step 'env_provisioning'
   → Pending → InProgress → Completed (sync)
   → directive: advance()

5. Activation: start step 'account_activation'
   → Completed, directive: complete()
   → ProcessCompleted event!

=== DONE ===
```

### bin/run-loyalty.php

```
=== PROGRAM LOJALNOŚCIOWY ===

1. Kampania: "Wiosna 2025"

2. Zdarzenie: Zakup 250 zł (participantId: USR-001)
   → IncentiveAction created (type: order_placed)
   → OrderPointsRule: 25 punktów
   → Settled → PointsGranted event

3. Zdarzenie: Polecenie znajomego (participantId: USR-001)
   → ReferralBonusRule: 50 punktów bonus
   → Settled → PointsGranted event

4. Zdarzenie: Zakup 1500 zł (participantId: USR-001)
   → OrderPointsRule: 150 punktów + nagroda "Darmowa dostawa"
   → Settled → PointsGranted + RewardGranted events

5. Chargeback: cofnięcie zakupu #2
   → Reversed → PointsDebited event (150 punktów cofnięte)

=== DONE ===
```

---

## Zasady implementacji

1. **PHP 8.4** — enum, readonly class, named arguments, match, `array_any()`, `array_all()`
2. **Bez frameworka** — żadnego Symfony, Laravel, żadnego kontenera DI. Ręczne wiring w bin/ skryptach.
3. **In-memory** — wszystkie repozytoria trzymają dane w tablicach PHP. Brak bazy danych.
4. **Testy PHPUnit** — każda klasa domenowa ma testy. Testy polityk konfliktów. Testy lifecycle dispatchera.
5. **Domena NIE wie o infrastrukturze** — lifecycle handlery, vendor clients, repozytoria to osobna warstwa
6. **OnboardingStep DZIEDZICZY** po Action (maszyna stanów pasuje 1:1)
7. **IncentiveAction NIE DZIEDZICZY** po Action (inna maszyna stanów: 6 vs 8 stanów) — kompozycja, ten sam wzorzec
8. **OutcomeDirective** na blueprincie — deklaratywna definicja "co dalej" per outcome
9. **DirectiveConflictPolicy** per scenariusz — Enterprise → EscalateOnConflictPolicy, SME → TerminalWinsPolicy
10. **PartySignature** — vendor jako outcomeApprover, audit trail w modelu domenowym
11. **Vendor clients** — interfejsy + Fake implementacje do prototypu (in-memory, symulują odpowiedzi)
12. **Domain events** z `releaseEvents()` — StepCompleted, StageAdvanced, ProcessCompleted, PointsGranted, RewardGranted, PointsDebited

## Czego NIE robić

- Nie dodawaj frameworka — czysty PHP z Composer autoloadem
- Nie dodawaj bazy danych — in-memory wystarcza
- Nie implementuj HTTP — bin/ skrypty wystarczą jako demo
- Nie twórz osobnych pakietów — jeden projekt, trzy namespace'y (SharedKernel, Onboarding, Loyalty)
- Nie overengineeruj — to jest prototyp demonstracyjny, nie produkcja
- Nie pomijaj testów — bez testów prototyp nie jest "działający"
