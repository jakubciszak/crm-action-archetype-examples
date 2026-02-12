<?php

declare(strict_types=1);

namespace CrmArchetype\Archetype;

class CustomerServiceCase
{
    /** @var CommunicationThread[] */
    private array $threads = [];

    private bool $isOpen = true;
    private \DateTimeImmutable $start;
    private ?\DateTimeImmutable $end = null;

    public function __construct(
        private readonly string $title,
        private readonly string $briefDescription,
        private readonly string $raisedBy,
        private readonly string $priority = 'medium',
    ) {
        $this->start = new \DateTimeImmutable();
    }

    public function addThread(CommunicationThread $thread): void
    {
        $this->threads[] = $thread;
    }

    /** @return CommunicationThread[] */
    public function threads(): array
    {
        return $this->threads;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->end = new \DateTimeImmutable();
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function briefDescription(): string
    {
        return $this->briefDescription;
    }

    public function raisedBy(): string
    {
        return $this->raisedBy;
    }

    public function priority(): string
    {
        return $this->priority;
    }
}
