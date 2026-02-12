<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Loyalty\Domain\IncentiveAction;
use Loyalty\Domain\LoyaltyCampaign;
use Loyalty\Infrastructure\InMemoryLoyaltyCampaignRepository;
use Loyalty\Infrastructure\Rules\OrderPointsRule;
use Loyalty\Infrastructure\Rules\ReferralBonusRule;

echo "=== PROGRAM LOJALNOŚCIOWY ===\n\n";

// --- Wiring ---
$campaignRepo = new InMemoryLoyaltyCampaignRepository();
$rules = [new OrderPointsRule(), new ReferralBonusRule()];

// 1. Kampania
$campaign = new LoyaltyCampaign('CAMP-001', 'Wiosna 2025');
$campaignRepo->save($campaign);
echo "1. Kampania: \"Wiosna 2025\"\n\n";

// 2. Zakup 250 zł
echo "2. Zdarzenie: Zakup 250 zł (participantId: USR-001)\n";
$action1 = new IncentiveAction(
    id: 'ACT-001',
    actionType: 'order_placed',
    payload: ['totalAmountCents' => 25000, 'orderId' => 'ORD-001'],
    participantId: 'USR-001',
    occurredAt: new \DateTimeImmutable(),
);
$action1->evaluate(...$rules);
$action1->settle();
$campaign->recordAction($action1);

$events1 = $action1->releaseEvents();
$points1 = $action1->decision()->journalEntries[0]->points;
echo "   → IncentiveAction created (type: order_placed)\n";
echo "   → OrderPointsRule: {$points1} punktów\n";
echo "   → Settled → " . (new \ReflectionClass($events1[0]))->getShortName() . " event\n\n";

// 3. Polecenie znajomego
echo "3. Zdarzenie: Polecenie znajomego (participantId: USR-001)\n";
$action2 = new IncentiveAction(
    id: 'ACT-002',
    actionType: 'referral',
    payload: ['referredUserId' => 'USR-002'],
    participantId: 'USR-001',
    occurredAt: new \DateTimeImmutable(),
);
$action2->evaluate(...$rules);
$action2->settle();
$campaign->recordAction($action2);

$events2 = $action2->releaseEvents();
$points2 = $action2->decision()->journalEntries[0]->points;
echo "   → ReferralBonusRule: {$points2} punktów bonus\n";
echo "   → Settled → " . (new \ReflectionClass($events2[0]))->getShortName() . " event\n\n";

// 4. Zakup 1500 zł
echo "4. Zdarzenie: Zakup 1500 zł (participantId: USR-001)\n";
$action3 = new IncentiveAction(
    id: 'ACT-003',
    actionType: 'order_placed',
    payload: ['totalAmountCents' => 150000, 'orderId' => 'ORD-002'],
    participantId: 'USR-001',
    occurredAt: new \DateTimeImmutable(),
);
$action3->evaluate(...$rules);
$action3->settle();
$campaign->recordAction($action3);

$events3 = $action3->releaseEvents();
$points3 = $action3->decision()->journalEntries[0]->points;
$rewardCount = count($action3->decision()->rewardGrants);
echo "   → OrderPointsRule: {$points3} punktów + nagroda \"Darmowa dostawa\"\n";
echo "   → Settled → ";
$eventNames = array_map(fn($e) => (new \ReflectionClass($e))->getShortName(), $events3);
echo implode(' + ', $eventNames) . " events\n\n";

// 5. Bilans przed chargebackiem
$orderStream = $campaign->findStream('order_placed');
$referralStream = $campaign->findStream('referral');
$totalBefore = $orderStream->totalSettledPoints() + $referralStream->totalSettledPoints();
echo "5. Bilans przed chargebackiem:\n";
echo "   → order_placed: {$orderStream->totalSettledPoints()} pkt (settled)\n";
echo "   → referral:     {$referralStream->totalSettledPoints()} pkt (settled)\n";
echo "   → RAZEM:        {$totalBefore} pkt\n\n";

// 6. Chargeback
echo "6. Chargeback: cofnięcie zakupu ORD-002 (150 pkt)\n";
$action3->reverse('Chargeback - zwrot zamówienia ORD-002');
$events4 = $action3->releaseEvents();
foreach ($events4 as $event) {
    $eventName = (new \ReflectionClass($event))->getShortName();
    echo "   → {$eventName}";
    if ($event instanceof \Loyalty\Domain\PointsDebited) {
        echo " (participantId: {$event->participantId}, -{$event->entry->points} pkt, reason: {$event->reason})";
    }
    echo "\n";
}

// 7. Bilans po chargebacku
$totalAfter = $orderStream->totalSettledPoints() + $referralStream->totalSettledPoints();
echo "\n7. Bilans po chargebacku:\n";
echo "   → order_placed: {$orderStream->totalSettledPoints()} pkt (settled)\n";
echo "   → referral:     {$referralStream->totalSettledPoints()} pkt (settled)\n";
echo "   → RAZEM:        {$totalAfter} pkt (było {$totalBefore}, odjęto {$points3})\n";

$campaignRepo->save($campaign);

echo "\n=== DONE ===\n";
