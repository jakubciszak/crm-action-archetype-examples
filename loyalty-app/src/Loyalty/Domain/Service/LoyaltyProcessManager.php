<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Service;

use App\Loyalty\Domain\Event\DomainEvent;
use App\Loyalty\Domain\Event\IncentiveReversed;
use App\Loyalty\Domain\Event\IncentiveSettled;
use App\Loyalty\Domain\Model\ActionOccurred;
use App\Loyalty\Domain\Model\ActivityCategory;
use App\Loyalty\Domain\Model\CategoryType;
use App\Loyalty\Domain\Model\IncentiveAction;
use App\Loyalty\Domain\Model\IncentiveDecision;
use App\Loyalty\Domain\Model\LoyaltyCampaign;
use App\SharedKernel\CrmArchetype\Model\Outcome;
use App\SharedKernel\CrmArchetype\Model\PartySignature;

/**
 * Zarządza przepływem procesu lojalnościowego.
 *
 * W przeciwieństwie do onboardingu (sekwencyjny), loyalty jest event-driven.
 * Każde zdarzenie jest niezależne — struktura identyczna, flow inny.
 */
final class LoyaltyProcessManager
{
    /** @var DomainEvent[] */
    private array $recordedEvents = [];

    private int $idCounter = 0;

    public function __construct(
        private readonly PointsCalculator $pointsCalculator,
        private readonly DoublePointsRule $doublePointsRule,
    ) {
    }

    /**
     * Rejestruje zdarzenie (zakup, polecenie, recenzja) i tworzy IncentiveAction.
     */
    public function recordActivity(
        LoyaltyCampaign $campaign,
        CategoryType $categoryType,
        string $memberId,
        string $eventType,
        array $eventData = [],
    ): IncentiveAction {
        if (!$campaign->isActive()) {
            throw new \DomainException('Kampania nie jest aktywna.');
        }

        $category = $campaign->findCategory($categoryType);
        if ($category === null) {
            throw new \DomainException(sprintf('Kategoria %s nie istnieje w kampanii.', $categoryType->value));
        }

        $event = new ActionOccurred(
            $this->nextId(),
            $memberId,
            $eventType,
            $eventData,
        );

        $incentive = new IncentiveAction(
            $this->nextId(),
            $memberId,
            $eventType,
        );

        $event->addIncentiveAction($incentive);
        $category->recordEvent($event);

        return $incentive;
    }

    /**
     * Ewaluuje IncentiveAction: oblicza punkty i przygotowuje do rozliczenia.
     */
    public function evaluateAndApprove(
        IncentiveAction $action,
        CategoryType $category,
        float $baseAmount,
        bool $isGoldMember,
        PartySignature $evaluator,
    ): void {
        $action->evaluate($evaluator);

        $context = [
            'order_pln' => $baseAmount,
            'is_gold_member' => $isGoldMember,
        ];

        $points = $this->doublePointsRule->applies($context)
            ? $this->pointsCalculator->calculateWithBonus($category, $baseAmount, $this->doublePointsRule->bonusMultiplier())
            : $this->pointsCalculator->calculate($category, $baseAmount);

        $outcome = IncentiveDecision::pointsGranted(
            $points,
            $action->memberId(),
            sprintf('Punkty za %s: %d PLN', $action->eventType(), (int) $baseAmount),
        );

        $action->approveForSettlement($outcome);
    }

    /**
     * Rozlicza IncentiveAction — emituje efekty biznesowe.
     */
    public function settle(IncentiveAction $action, LoyaltyCampaign $campaign): void
    {
        $outcomes = $action->actualOutcomes();
        $lastOutcome = end($outcomes) ?: throw new \DomainException('Brak outcome do rozliczenia.');

        $action->settle($lastOutcome);

        $this->recordedEvents[] = new IncentiveSettled(
            $campaign->id(),
            $action->id(),
            $action->memberId(),
            $lastOutcome->code(),
            $action->totalPointsGranted(),
        );
    }

    /**
     * Cofnięcie (chargeback) — reverse.
     */
    public function reverse(IncentiveAction $action, string $reason): void
    {
        $action->reverse($reason);

        $this->recordedEvents[] = new IncentiveReversed(
            $action->id(),
            $action->memberId(),
            $reason,
        );
    }

    /** @return DomainEvent[] */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function nextId(): string
    {
        return 'loy-' . ++$this->idCounter;
    }
}
