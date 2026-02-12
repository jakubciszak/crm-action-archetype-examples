<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final class ActivityStream
{
    /** @var IncentiveAction[] */
    private array $actions = [];

    public function __construct(
        private readonly string $activityType,
    ) {}

    public function activityType(): string
    {
        return $this->activityType;
    }

    public function addAction(IncentiveAction $action): void
    {
        $this->actions[] = $action;
    }

    /** @return IncentiveAction[] */
    public function actions(): array
    {
        return $this->actions;
    }

    public function totalSettledPoints(): int
    {
        $total = 0;
        foreach ($this->actions as $action) {
            if ($action->state() === IncentiveActionState::Settled && $action->decision() !== null) {
                foreach ($action->decision()->journalEntries as $entry) {
                    $total += $entry->points;
                }
            }
        }
        return $total;
    }
}
