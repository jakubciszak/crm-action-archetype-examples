<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Model;

use App\SharedKernel\CrmArchetype\Model\CustomerServiceCase;

/**
 * OnboardingCase — CustomerServiceCase w domenie onboardingu.
 * Cały proces dla jednego klienta B2B.
 *
 * Enterprise ma KYC z trzema krokami i AML screening.
 * SME ma uproszczone KYC automatyczne.
 * Scenariusz dobierany na podstawie profilu klienta — naturalne miejsce na Rule.
 */
class OnboardingCase extends CustomerServiceCase
{
    private CustomerType $customerType;
    private string $customerId;
    private ?OnboardingStage $currentStage = null;

    /** @var OnboardingStage[] */
    private array $stages = [];

    public function __construct(
        string $id,
        string $customerId,
        CustomerType $customerType,
        int $priority = 0,
    ) {
        parent::__construct($id, $priority);
        $this->customerId = $customerId;
        $this->customerType = $customerType;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function customerType(): CustomerType
    {
        return $this->customerType;
    }

    public function addStage(OnboardingStage $stage): void
    {
        $this->stages[] = $stage;
        $this->addThread($stage);
    }

    /** @return OnboardingStage[] */
    public function stages(): array
    {
        return $this->stages;
    }

    public function currentStage(): ?OnboardingStage
    {
        return $this->currentStage;
    }

    public function advanceToStage(OnboardingStage $stage): void
    {
        if (!in_array($stage, $this->stages, true)) {
            throw new \DomainException('Stage nie należy do tego Case.');
        }

        if ($this->currentStage !== null && !$this->currentStage->isCompleted()) {
            throw new \DomainException('Obecny stage nie jest zakończony.');
        }

        $this->currentStage = $stage;
    }

    public function completeCurrentStage(): void
    {
        if ($this->currentStage === null) {
            throw new \DomainException('Brak aktywnego stage.');
        }

        $this->currentStage->complete();
    }

    /**
     * Sprawdza czy cały onboarding jest zakończony — wszystkie stage'e completed.
     */
    public function isFullyCompleted(): bool
    {
        if (count($this->stages) === 0) {
            return false;
        }

        foreach ($this->stages as $stage) {
            if (!$stage->isCompleted()) {
                return false;
            }
        }

        return true;
    }

    public function reject(): void
    {
        $this->status = 'rejected';
    }
}
