<?php

declare(strict_types=1);

namespace App\SharedKernel\CrmArchetype\Model;

/**
 * CustomerServiceCase — kontener procesu z priorytetem i cyklem życia.
 * Najwyższy poziom hierarchii: Case → Thread → Communication → Action → Outcome.
 */
abstract class CustomerServiceCase
{
    /** @var CommunicationThread[] */
    protected array $threads = [];
    protected string $status = 'open';
    protected \DateTimeImmutable $createdAt;

    public function __construct(
        protected readonly string $id,
        protected readonly int $priority = 0,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function status(): string
    {
        return $this->status;
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
        $this->status = 'closed';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
