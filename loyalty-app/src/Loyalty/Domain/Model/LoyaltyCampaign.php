<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Model;

use App\Loyalty\Domain\State\CampaignStatus;

/**
 * LoyaltyCampaign — CustomerServiceCase w domenie loyalty.
 * Sezon, kampania promocyjna z kategoriami aktywności.
 */
class LoyaltyCampaign
{
    private CampaignStatus $status;

    /** @var ActivityCategory[] */
    private array $categories = [];

    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly \DateTimeImmutable $startDate,
        private readonly \DateTimeImmutable $endDate,
    ) {
        $this->status = CampaignStatus::Draft;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): CampaignStatus
    {
        return $this->status;
    }

    public function startDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function addCategory(ActivityCategory $category): void
    {
        $this->categories[] = $category;
    }

    /** @return ActivityCategory[] */
    public function categories(): array
    {
        return $this->categories;
    }

    public function findCategory(CategoryType $type): ?ActivityCategory
    {
        foreach ($this->categories as $category) {
            if ($category->type() === $type) {
                return $category;
            }
        }

        return null;
    }

    public function activate(): void
    {
        $this->transitionTo(CampaignStatus::Active);
    }

    public function suspend(): void
    {
        $this->transitionTo(CampaignStatus::Suspended);
    }

    public function complete(): void
    {
        $this->transitionTo(CampaignStatus::Completed);
    }

    public function isActive(): bool
    {
        return $this->status === CampaignStatus::Active;
    }

    private function transitionTo(CampaignStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(sprintf(
                'Niedozwolone przejście stanu kampanii: %s → %s',
                $this->status->value,
                $target->value,
            ));
        }

        $this->status = $target;
    }
}
