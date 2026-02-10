<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Model;

use App\SharedKernel\CrmArchetype\Model\CommunicationThread;

/**
 * OnboardingStage — CommunicationThread w domenie onboardingu.
 * Fazy: KYC, Umowa, Setup, Szkolenie.
 *
 * Stage może mieć wiele Stepów. Jakie Stepy i jakie Stage'e
 * zależy od scenariusza (Enterprise vs SME).
 */
class OnboardingStage extends CommunicationThread
{
    private StageType $type;
    private int $order;
    private bool $completed = false;

    public function __construct(string $id, StageType $type, int $order)
    {
        parent::__construct($id, $type->value);
        $this->type = $type;
        $this->order = $order;
    }

    public function type(): StageType
    {
        return $this->type;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function complete(): void
    {
        $this->completed = true;
    }

    /**
     * Pętla zwrotna: negatywny Outcome generuje nową Communication + Action.
     */
    public function requestSupplement(string $triggerId, OnboardingStep $supplementStep): OnboardingTrigger
    {
        $trigger = OnboardingTrigger::supplementRequest($triggerId);
        $trigger->addAction($supplementStep);
        $this->addCommunication($trigger);

        return $trigger;
    }

    /**
     * Sprawdza czy wszystkie stepy w stage są zakończone (completed lub escalated).
     */
    public function allStepsTerminal(): bool
    {
        foreach ($this->communications() as $communication) {
            foreach ($communication->actions() as $action) {
                if ($action instanceof OnboardingStep && !$action->isTerminal()) {
                    return false;
                }
            }
        }

        return count($this->communications()) > 0;
    }

    /**
     * Sprawdza czy którykolwiek step został odrzucony.
     */
    public function hasRejectedStep(): bool
    {
        foreach ($this->communications() as $communication) {
            foreach ($communication->actions() as $action) {
                if ($action instanceof OnboardingStep && $action->isRejected()) {
                    return true;
                }
            }
        }

        return false;
    }
}
