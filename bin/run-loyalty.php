<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Loyalty\Domain\IncentiveAction;
use Loyalty\Domain\LoyaltyCampaign;
use Loyalty\Domain\PointsDebitedForReturn;
use Loyalty\Domain\PointWallet;
use Loyalty\Domain\WalletEntryStatus;
use Loyalty\Infrastructure\InMemoryLoyaltyCampaignRepository;
use Loyalty\Infrastructure\Rules\OrderPointsRule;
use Loyalty\Infrastructure\Rules\ReferralBonusRule;

echo "=== PROGRAM LOJALNOŚCIOWY (portfel pending → active) ===\n\n";

// --- Wiring ---
$campaignRepo = new InMemoryLoyaltyCampaignRepository();
$rules = [new OrderPointsRule(), new ReferralBonusRule()];
$now = new \DateTimeImmutable('2025-05-12 10:00:00');

// 1. Kampania + portfel
$campaign = new LoyaltyCampaign('CAMP-001', 'Wiosna 2025');
$campaignRepo->save($campaign);
$wallet = new PointWallet('USR-001');

echo "1. Kampania: \"Wiosna 2025\", portfel USR-001 utworzony\n\n";

// ─── 2. Zamówienie ORD-001 (3 produkty) ───
echo "2. Zamówienie ORD-001 (3 produkty, return period: 14 dni):\n";
$orderAction = new IncentiveAction(
    id: 'ACT-001',
    actionType: 'order_placed',
    payload: [
        'orderId' => 'ORD-001',
        'lines' => [
            ['lineId' => 'L1', 'productName' => 'Laptop Dell XPS',   'amountCents' => 300000],
            ['lineId' => 'L2', 'productName' => 'Mysz Logitech MX',  'amountCents' => 15000],
            ['lineId' => 'L3', 'productName' => 'Klawiatura Keychron','amountCents' => 35000],
        ],
    ],
    participantId: 'USR-001',
    occurredAt: $now,
);
$orderAction->evaluate(...$rules);
$orderAction->settle();
$campaign->recordAction($orderAction);

// Punkty z zamówienia → PENDING w portfelu
$wallet->creditPending('ORD-001', $orderAction->decision()->journalEntries);

echo "   Linie zamówienia:\n";
foreach ($orderAction->decision()->journalEntries as $entry) {
    echo "   → {$entry->sourceItemRef}: {$entry->label}";
    $amountZl = 0;
    foreach ($orderAction->payload()['lines'] as $line) {
        if ($line['lineId'] === $entry->sourceItemRef) {
            $amountZl = $line['amountCents'] / 100;
        }
    }
    echo " ({$amountZl} zł → {$entry->points} pkt)\n";
}
$totalOrderPts = array_sum(array_map(fn($e) => $e->points, $orderAction->decision()->journalEntries));
echo "   Razem: {$totalOrderPts} pkt → PENDING (return period do {$now->modify('+14 days')->format('Y-m-d')})\n";

// Nagroda?
if ($orderAction->decision()->rewardGrants !== []) {
    echo "   + nagroda: \"{$orderAction->decision()->rewardGrants[0]->description}\"\n";
}
echo "\n";

// ─── 3. Polecenie znajomego ───
echo "3. Polecenie znajomego (USR-001 → USR-002):\n";
$referralAction = new IncentiveAction(
    id: 'ACT-002',
    actionType: 'referral',
    payload: ['referredUserId' => 'USR-002'],
    participantId: 'USR-001',
    occurredAt: $now,
);
$referralAction->evaluate(...$rules);
$referralAction->settle();
$campaign->recordAction($referralAction);

// Punkty z referrala → od razu ACTIVE, wygasają za 3 miesiące
$referralEntry = $referralAction->decision()->journalEntries[0];
$referralExpiresAt = $now->modify('+3 months');
$wallet->creditActive($referralEntry, $referralExpiresAt);

echo "   → ReferralBonusRule: {$referralEntry->points} pkt → ACTIVE (wygasa: {$referralExpiresAt->format('Y-m-d')})\n\n";

// ─── 4. Portfel po 2 zdarzeniach ───
echo "4. Portfel po 2 zdarzeniach:\n";
echo "   PENDING: {$wallet->pendingBalance()} pkt\n";
foreach ($wallet->entriesByStatus(WalletEntryStatus::Pending) as $e) {
    echo "     • {$e->sourceRef}/{$e->sourceItemRef} {$e->label}: {$e->points} pkt\n";
}
echo "   ACTIVE:  {$wallet->activeBalance()} pkt\n";
foreach ($wallet->entriesByStatus(WalletEntryStatus::Active) as $e) {
    echo "     • {$e->reason}: {$e->points} pkt (wygasa: {$e->expiresAt()->format('Y-m-d')})\n";
}
echo "   RAZEM:   " . ($wallet->pendingBalance() + $wallet->activeBalance()) . " pkt";
echo " (w tym {$wallet->activeBalance()} dostępnych)\n\n";

// ─── 5. Zwrot produktu: Mysz Logitech MX ───
echo "5. Zwrot produktu: Mysz Logitech MX (ORD-001/L2, 15 pkt) w okresie zwrotów:\n";
$debitedPoints = $wallet->debitItem('ORD-001', 'L2');
$walletEvents = $wallet->releaseEvents();
foreach ($walletEvents as $event) {
    if ($event instanceof PointsDebitedForReturn) {
        echo "   → PointsDebitedForReturn: -{$event->points} pkt";
        echo " ({$event->sourceRef}/{$event->sourceItemRef} {$event->label})\n";
    }
}
echo "   PENDING po zwrocie: {$wallet->pendingBalance()} pkt\n\n";

// ─── 6. Koniec okresu zwrotów ORD-001 ───
$returnPeriodEnd = $now->modify('+14 days');
$orderExpiresAt = $returnPeriodEnd->modify('+6 months');
echo "6. Koniec okresu zwrotów ORD-001 ({$returnPeriodEnd->format('Y-m-d')}):\n";
$activatedPts = $wallet->activateSource('ORD-001', $orderExpiresAt);
echo "   → {$activatedPts} pkt: PENDING → ACTIVE (wygasa: {$orderExpiresAt->format('Y-m-d')})\n\n";

// ─── 7. Portfel końcowy ───
echo "7. Portfel końcowy:\n";
echo "   ACTIVE: {$wallet->activeBalance()} pkt\n";
foreach ($wallet->entriesByStatus(WalletEntryStatus::Active) as $e) {
    $source = $e->sourceRef
        ? "{$e->sourceRef}/{$e->sourceItemRef} {$e->label}"
        : $e->reason;
    echo "     • {$source}: {$e->points} pkt (wygasa: {$e->expiresAt()->format('Y-m-d')})\n";
}
echo "   PENDING: {$wallet->pendingBalance()} pkt\n";

$debitedEntries = $wallet->entriesByStatus(WalletEntryStatus::Debited);
if ($debitedEntries !== []) {
    $debitedSum = array_sum(array_map(fn($e) => $e->points, $debitedEntries));
    echo "   DEBITED: {$debitedSum} pkt (zwroty)\n";
    foreach ($debitedEntries as $e) {
        echo "     • {$e->sourceRef}/{$e->sourceItemRef} {$e->label}: {$e->points} pkt\n";
    }
}

// ─── 8. Symulacja upływu czasu — wygaśnięcie referrala po 3 miesiącach ───
echo "\n8. Symulacja: +4 miesiące ({$now->modify('+4 months')->format('Y-m-d')}):\n";
$future = $now->modify('+4 months');
$expiredPts = $wallet->expireEntries($future);
echo "   → Wygasło: {$expiredPts} pkt (referral, 3-miesięczna ważność)\n";
echo "   ACTIVE po wygaśnięciu: {$wallet->activeBalance()} pkt\n";
foreach ($wallet->entriesByStatus(WalletEntryStatus::Active) as $e) {
    $source = $e->sourceRef
        ? "{$e->sourceRef}/{$e->sourceItemRef} {$e->label}"
        : $e->reason;
    echo "     • {$source}: {$e->points} pkt (wygasa: {$e->expiresAt()->format('Y-m-d')})\n";
}

$campaignRepo->save($campaign);

echo "\n=== DONE ===\n";
