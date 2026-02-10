<?php

declare(strict_types=1);

namespace CrmArchetype\Archetype;

class Communication
{
    /** @var Action[] */
    private array $actions = [];

    public function __construct(
        private readonly string $content,
        private readonly \DateTimeImmutable $dateSent,
        private ?\DateTimeImmutable $dateReceived = null,
        private readonly ?string $fromAddress = null,
        private readonly ?string $toAddress = null,
    ) {}

    public function generateAction(Action $action): void
    {
        $this->actions[] = $action;
    }

    /** @return Action[] */
    public function actions(): array
    {
        return $this->actions;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function dateSent(): \DateTimeImmutable
    {
        return $this->dateSent;
    }

    public function dateReceived(): ?\DateTimeImmutable
    {
        return $this->dateReceived;
    }

    public function markReceived(?\DateTimeImmutable $at = null): void
    {
        $this->dateReceived = $at ?? new \DateTimeImmutable();
    }
}
