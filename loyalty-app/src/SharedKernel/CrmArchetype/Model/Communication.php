<?php

declare(strict_types=1);

namespace App\SharedKernel\CrmArchetype\Model;

/**
 * Communication — trigger/zdarzenie, które uruchamia Action.
 * Generuje zero do wielu Actions.
 */
class Communication
{
    /** @var Action[] */
    private array $actions = [];
    private \DateTimeImmutable $triggeredAt;

    public function __construct(
        private readonly string $id,
        private readonly string $source,
    ) {
        $this->triggeredAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function triggeredAt(): \DateTimeImmutable
    {
        return $this->triggeredAt;
    }

    public function addAction(Action $action): void
    {
        $this->actions[] = $action;
    }

    /** @return Action[] */
    public function actions(): array
    {
        return $this->actions;
    }
}
