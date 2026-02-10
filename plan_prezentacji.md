# Plan prezentacji — 45 minut

## Archetyp CRM Activity jako uniwersalne narzędzie zarządzania logiką w różnych domenach

---

## SEKCJA 1: Głęboki model i szukanie wzorców
**Czas: 0:00 – 5:00 (5 min) | Slajdy 1–5**

### 1.1 Otwarcie (slajd 1)
**[1 min]**
- Przedstawienie się, temat, kontekst
- **Powiedzieć:** „Projektując systemy, łatwo wpaść w pułapkę wyjątkowości — skupiamy się na szczegółach konkretnej domeny, nie widząc głębszego wzorca pod spodem. Dziś pokażę jak z tej pułapki wyjść."

### 1.2 Agenda (slajd 2)
**[30 sek]**
- Szybki przegląd struktury, nie czytać punkt po punkcie
- **Powiedzieć:** „Zaczniemy od idei głębokiego modelu, potem anatomia archetypu, dwa case study, i na końcu jak to wszystko spina się z wzorcami State i Rule."

### 1.3 Pułapka wyjątkowości (slajd 3 — sekcja tytułowa)
**[15 sek]**
- Przejście, zbudowanie napięcia

### 1.4 Od płytkiego do głębokiego modelu (slajd 4)
**[2 min]**
- Trzy karty: Płytki model → Refaktoring → Głęboki model
- **Powiedzieć:** „Naturalna tendencja — każda domena dostaje model od zera. Onboarding ma swoje OnboardingStepy, loyalty ma LoyaltyActivity, serwis ma Ticket. Ale jeśli spojrzeć głębiej, wszystkie mają: coś do zrobienia, ktoś to robi, jest wynik, jest status."
- **Powiedzieć:** „To jest moment breakthrough z DDD — odkrycie głębokiego modelu. Im głębszy model, tym więcej domen obsługuje. I wbrew intuicji — abstrakcja nie utrudnia, a upraszcza."

### 1.5 Archetypy vs GoF vs DDD (slajd 5)
**[1:15 min]**
- Trzy kolumny: GoF = jak organizować kod, DDD = jak strukturyzować model, Archetypy = co modelować
- **Powiedzieć:** „GoF powie Ci żebyś użył Strategy. DDD powie że to Aggregate. Ale żaden z nich nie powie Ci, że każdy proces biznesowy to Communication → Action → Outcome. Archetypy zawierają wiedzę domenową w pakiecie. Ktoś już przeszedł tę drogę refaktoringów za nas."

---

## SEKCJA 2: Anatomia archetypu CRM Activity
**Czas: 5:00 – 13:00 (8 min) | Slajdy 6–8**

### 2.1 Tytuł sekcji (slajd 6)
**[15 sek]**

### 2.2 Diagram klas Action & Outcome (slajd 7)
**[4 min]**
- Communication → Action → Outcome + PartySignature
- **Powiedzieć:** „Communication generuje zero do wielu Actions. Każda Action ma dwa typy Outcomes:"
  - `possibleOutcomes` — definiowane z góry, tworzą mapę znanych ścieżek procesu. „Zanim rozpoczniesz Action, wiesz jakie wyniki są możliwe."
  - `actualOutcomes` — rejestrowane w runtime, każda Action MUSI mieć co najmniej jeden. „Na starcie actualOutcome to 'Pending'."
- **Powiedzieć:** „Status pending/open/closed — to jest uproszczenie z archetypu. Za chwilę pokażę, że w realnym systemie to za mało."
- **Powiedzieć:** „Ta triada — Action, Outcome, Status — opisuje KAŻDY proces, w którym ktoś robi coś z jakimś wynikiem. To jest ten głęboki model."

### 2.3 CustomerServiceCase — kontener procesu (slajd 8)
**[3:45 min]**
- Hierarchia: Case → CommunicationThread → Communication → Action → Outcome
- Tabela mapowania na abstrakcje
- **Powiedzieć:** „Case to kontener z priorytetem i cyklem życia. CommunicationThread to faza albo etap. Communication to trigger — zdarzenie, które uruchamia działanie."
- **Powiedzieć:** „I teraz klucz — te same koncepty: kontener, faza, trigger, krok, wynik — są w KAŻDYM procesie. Za chwilę pokażę to na dwóch zupełnie różnych domenach."

---

## SEKCJA 3: Case Study — Onboarding B2B
**Czas: 13:00 – 22:00 (9 min) | Slajdy 9–12**

### 3.1 Tytuł sekcji (slajd 9)
**[15 sek]**

### 3.2 Mapowanie CRM → Onboarding (slajd 10)
**[3:45 min]**
- Diagram: lewa strona = archetyp, prawa = domena onboardingu
- **Powiedzieć:** „Mapowanie jest jeden do jednego:"
  - CustomerServiceCase → OnboardingCase — cały proces dla jednego klienta
  - CommunicationThread → OnboardingStage — KYC, Umowa, Setup, Szkolenie
  - Communication → OnboardingTrigger — wniosek klienta, event systemowy
  - Action → OnboardingStep — konkretny krok z possibleOutcomes
  - Outcome → StepResult — Zaakceptowany / Do uzupełnienia / Odrzucony
- **Powiedzieć:** „Nie trzeba nic wymyślać — archetyp daje gotową strukturę."
- **Powiedzieć:** „I co ważne — Stage może mieć wiele Step'ów. A jakie Stepy i jakie Stage'e — to zależy od scenariusza. Enterprise ma KYC z trzema krokami i AML screening. SME ma uproszczone KYC automatyczne. Scenariusz dobierany jest na podstawie profilu klienta — to jest naturalne miejsce na Rule."

### 3.3 Przepływ procesu (slajd 11)
**[4 min]**
- 4 fazy z outcomes, tabela przykładowej Action
- **Powiedzieć:** „Każda faza ma swoje possibleOutcomes. Pozytywny — idziemy dalej. Negatywny — i tu jest piękno archetypu — negatywny Outcome generuje nową Communication. A nowa Communication generuje nową Action. Pętla zwrotna jest wbudowana w archetyp!"
- **Powiedzieć (tabela):** „Konkretna instancja: Action 'Weryfikacja KYC dla Acme Corp'. Status przechodzi pending → open → closed. possibleOutcomes zdefiniowane z góry. actualOutcome: 'Wymagane uzupełnienie', reason: 'Brak KRS'. I ten actualOutcome automatycznie generuje nowy krok — zbierz brakujące dokumenty."
- **Powiedzieć:** „possibleOutcomes to mapa procesu zdefiniowana deklaratywnie. actualOutcome to runtime — co się naprawdę stało."

### 3.4 KOD: OnboardingStep extends Action (slajd 12)
**[2 min]**
- Ciemny slajd z kodem + 3 adnotacje
- **Powiedzieć:** „OnboardingStep dziedziczy po Action — bo maszyna stanów pasuje 1:1. Domena dodaje swoją logikę, rdzeń daje mechanikę."
- **Powiedzieć:** „`fromBlueprint()` — step tworzony z deklaratywnego scenariusza. possibleOutcomes ładowane z blueprintu, znane zanim step się zacznie."
- **Powiedzieć:** „`requiresSupplement()` — sprawdza actualOutcomes w runtime. Jeśli 'DoUzupełnienia' — pętla zwrotna, nowa Action w tym samym Stage."

---

## SEKCJA 4: Case Study — Program Lojalnościowy
**Czas: 22:00 – 29:00 (7 min) | Slajdy 13–16**

### 4.1 Tytuł sekcji (slajd 13)
**[15 sek]**

### 4.2 Mapowanie CRM → Loyalty (slajd 14)
**[3:45 min]**
- Diagram: ten sam archetyp, inna domena
- **Powiedzieć:** „Ten sam archetyp, zupełnie inna domena:"
  - CustomerServiceCase → LoyaltyCampaign — sezon, kampania
  - CommunicationThread → ActivityCategory — Zakupy, Polecenia, Recenzje
  - Communication → ActionOccurred — event: zakup, check-in, polecenie
  - Action → IncentiveAction — nalicz punkty, przyznaj nagrodę
  - Outcome → IncentiveDecision — PointsGranted / RewardGrant / Odrzucone
- **Powiedzieć:** „Zwróćcie uwagę na fundamentalną różnicę: onboarding jest sekwencyjny — faza po fazie. Loyalty jest event-driven — każde zdarzenie jest niezależne. A struktura? Identyczna."

### 4.3 Porównanie domen side-by-side (slajd 15)
**[3 min]**
- Tabela porównawcza: Case, Thread, Action, Outcome, Status, Initiator
- **Powiedzieć:** „Tabela mówi sama za siebie — struktura identyczna, różnice w flow i regułach."
- **Powiedzieć (wiersz Status):** „I tu pojawia się kluczowy temat — Status/State. Onboarding: sekwencyjny, z OnHold i Escalated. Loyalty: event-driven, z Reversed (chargeback). Archetyp daje trzy statusy — to za mało. Potrzebujemy pełnej maszyny stanów. I to jest następna sekcja."
- **Powiedzieć:** „Głęboki model pozwala współdzielić rdzeń — wspólny model danych, wspólne API, wspólne testy — a domeny różnią się konfiguracją, nie kodem."

### 4.4 KOD: IncentiveAction — inna maszyna stanów (slajd 16)
**[2 min]**
- Ciemny slajd z kodem + 3 adnotacje
- **Powiedzieć:** „IncentiveAction NIE dziedziczy po Action — celowy wybór. Zupełnie inna maszyna stanów: 6 stanów zamiast 8. Ale ten sam wzorzec: state transitions + outcomes + guard conditions."
- **Powiedzieć:** „`settle()` — Outcome emituje efekty biznesowe. `IncentiveDecision` niesie journalEntries, rewardGrants, domain events."
- **Powiedzieć:** „`reverse()` — stan spoza archetypu. Chargeback → cofnięcie punktów. Głęboki model rozszerza archetyp o potrzeby domeny."
- **Powiedzieć:** „Dziedziczenie gdy maszyna stanów pasuje, kompozycja gdy nie pasuje. Wzorzec jest wspólny."

---

## SEKCJA 5: Outcomes i State w praktyce
**Czas: 29:00 – 39:00 (10 min) | Slajdy 17–21**

### 5.1 Tytuł sekcji (slajd 17)
**[15 sek]**

### 5.2 Outcomes jako mapa procesu (slajd 18)
**[3 min]**
- Dwa panele: Onboarding (outcomes sterują sekwencją) vs Loyalty (outcomes jako decyzje)
- **Powiedzieć (lewy panel):** „W onboardingu Outcome steruje przepływem. actualOutcome 'Zaakceptowane' → następna faza. 'Do uzupełnienia' → pętla zwrotna, nowa Communication. 'Odrzucone' → zamknij Case."
- **Powiedzieć (prawy panel):** „W loyalty Outcome to IncentiveDecision z konkretnymi efektami biznesowymi: journalEntries — zapis księgowy, rewardGrants — przyznane nagrody, events — eventy domenowe do dalszego przetwarzania."
- **Powiedzieć:** „W obu domenach Outcome to punkt decyzyjny — różnica jest w tym, co dalej. Onboarding: outcome steruje SEKWENCJĄ. Loyalty: outcome EMITUJE efekty."

### 5.3 State — rozbudowany cykl życia Action (slajd 19)
**[3 min]**
- Lewy panel: pending/open/closed (archetyp). Prawy: pełna maszyna stanów (8 stanów)
- **Powiedzieć:** „Archetyp daje trzy stany — to jest minimum. W realnym systemie potrzebujemy więcej."
- Przejść przez stany: Draft → Pending → InProgress → AwaitingApproval → Completed
- Boczne ścieżki: OnHold (wstrzymany), Failed (retry), Escalated (SLA)
- **Powiedzieć:** „Każde przejście ma guard conditions i side effects. submit() waliduje, start() przypisuje zasoby, retry() wraca z Failed do InProgress z nowym kontekstem."
- **Powiedzieć (dolny box):** „State + Outcome = pełna kontrola. State mówi CO MOŻNA zrobić z Action w danym momencie. Outcome mówi CO SIĘ STAŁO i dokąd iść dalej. Przykład: InProgress + actualOutcome 'Do uzupełnienia' → stan wraca do Pending, nowa Action."

### 5.4 State w obu domenach (slajd 20)
**[1:45 min]**
- Dwa panele: Onboarding states vs Loyalty states
- **Powiedzieć:** „Różne stany, ale ten sam wzorzec. Onboarding: Draft → Pending → InProgress → AwaitingApproval → Completed, plus OnHold i Escalated. Loyalty: Received → Evaluating → AwaitingSettlement → Settled, plus Rejected i Reversed."
- **Powiedzieć:** „Pięć wspólnych zasad:"
  1. Interfejs State z metodą handle(event) — ten sam kontrakt
  2. Każdy stan definiuje dozwolone przejścia — guard conditions
  3. Przejście generuje Outcome
  4. State chroni invarianty: nie można Completed → InProgress
  5. Reversed/Escalated = stany, których archetyp nie przewiduje — głęboki model je dodaje
- **Powiedzieć:** „To właśnie znaczy głęboki model — archetyp daje fundament, State go konkretyzuje dla domeny."

### 5.5 KOD: Reakcja na zmianę stanu + async vendor (slajd 21)
**[2 min]**
- Lewy panel: interface `ActionLifecycleHandler` z `supports()` + `KycVendorHandler`
- Prawy panel: diagram 6-krokowego async flow
- **Powiedzieć:** „Różne typy Action wymagają różnych side-effectów przy zmianie stanu. KYC wymaga requestu do vendora. Podpisanie umowy — DocuSign. Provisioning — API infrastruktury."
- **Powiedzieć:** „Interfejs jest prosty: `supports()` mówi 'obsługuję TEN typ akcji w TYM przejściu stanowym'. `handle()` wykonuje side-effect."
- **Powiedzieć (diagram):** „KYC step przechodzi Pending → InProgress. Dispatcher znajduje handler, handler wysyła request do vendora, zwraca AwaitingCallback. Step zostaje w InProgress. Czas mija. Vendor odpowiada webhookiem. Webhook tworzy command, handler rejestruje Outcome na stepie."
- **Powiedzieć (punchline):** „I tu pięknie zamyka się archetyp — vendor staje się outcomeApprover, czyli PartySignature. Mamy audit trail w modelu domenowym. A domena nie wie i nie musi wiedzieć, że Outcome przyszedł z zewnątrz."

---

## SEKCJA 6: Kompozycja z Rule
**Czas: 39:00 – 42:00 (3 min) | Slajdy 22–23**

### 6.1 Tytuł sekcji (slajd 22)
**[15 sek]**

### 6.2 Rule — deklaratywne reguły (slajd 23)
**[2:45 min]**
- Lewa: mechanika zamek/klucz/ActivityRule. Prawa: przykłady. Dół: trójka State + Rule + Outcome
- **Powiedzieć:** „Rule to zamek — Propositions, Variables, Operators, ale BEZ wartości. RuleContext to klucz — te same elementy, ale Z wartościami. Pasuje? Reguła się odpala."
- **Powiedzieć:** „ActivityRule to zamek, który automatycznie otwiera drzwi — evaluate() zwraca true, wywołuje activity()."
- **Powiedzieć (przykłady):**
  - Onboarding EscalateKYC: IF kycDays > 5 AND isEnterprise THEN → State = Escalated
  - Loyalty DoublePoints: IF isGoldMember AND orderPLN > 500 THEN → Outcome = 2x PointsGranted
- **Powiedzieć:** „Reguły jako dane, nie kod. Zmiana progu = update w bazie, nie redeploy."
- **Powiedzieć (trójka):** „I tu zamyka się cały model. State mówi CO MOŻNA zrobić. Rule mówi KIEDY. Outcome rejestruje CO SIĘ STAŁO. Te trzy wzorce razem dają pełny, deklaratywny model sterowania procesem."

---

## SEKCJA 7: Wnioski i Q&A
**Czas: 42:00 – 45:00 (3 min) | Slajdy 24–25**

### 7.1 Wnioski (slajd 24)
**[2 min]**
- Cztery takeaway'e
- **Powiedzieć:**
  1. „**Głęboki model** — wbrew intuicji, szukanie uniwersalnych wzorców to najkrótsza droga. Archetypy to gotowy głęboki model — ktoś już przeszedł te refaktoringi za nas."
  2. „**State + Outcome** — maszyna stanów i mapa rozgałęzień dają pełną kontrolę cyklu życia procesu. W każdej domenie."
  3. „**Kompozycja wzorców** — Activity + State + Rule, każdy na swoim poziomie. Elastyczność bez overengineeringu."
  4. „**Reużywalność** — ten sam rdzeń obsługuje onboarding, loyalty, serwis, workflow. Mniej kodu, mniej błędów."
- **Powiedzieć:** „Następnym razem, gdy będziecie projektować 'krok procesu ze statusem i wynikiem' — zatrzymajcie się na chwilę. Prawdopodobnie macie do czynienia z głębokim modelem, który już ktoś opisał."

### 7.2 Q&A (slajd 25)
**[1 min + dyskusja]**
- **Powiedzieć:** „Szukajcie głębokiego modelu. Wbrew intuicji — to się opłaca."
- Pytania do publiczności:
  - „Kto stosował archetypy biznesowe?"
  - „W jakich domenach widzicie podobne struktury?"
  - „Kto miał moment breakthrough z głębokim modelem?"

---

## Notatki techniczne

| Element | Wartość |
|---------|---------|
| Łączny czas merytoryczny | ~43 min |
| Bufor na pytania z sali | ~2 min |
| Slajdów | 25 |
| Średni czas na slajd | ~1:48 min |
| Slajdy tytułowe sekcji | ~15 sek każdy |
| Najdłuższe slajdy | #7 (diagram klas, 4 min), #11 (przepływ, 4 min), #15 (porównanie, 4 min) |
| Slajdy z kodem | #12 (OnboardingStep), #16 (IncentiveAction), #21 (Lifecycle+Vendor) |

## Rytm prezentacji

- **Sekcje 1–2 (0–13 min):** Fundament — teoria i anatomia. Tempo spokojne, budowanie zrozumienia.
- **Sekcja 3 (13–22 min):** Onboarding + kod. Case study z mapowaniem, potem konkretny kod OnboardingStep. Pierwszy „a-ha moment".
- **Sekcja 4 (22–29 min):** Loyalty + kod. Kontrast z onboardingiem, potem IncentiveAction — „ten sam wzorzec, inna implementacja".
- **Sekcja 5 (29–39 min):** Outcomes, State, Lifecycle — kulminacja techniczna. Tu jest mięso. Zwolnić, dać czas. Slajd z async vendor jako punkt kulminacyjny — „archetyp obsługuje nawet async flow z zewnętrznymi vendorami".
- **Sekcje 6–7 (39–45 min):** Rule + wnioski — domknięcie. Szybko, zwięźle, mocny punchline.
