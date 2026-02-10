<?php

declare(strict_types=1);

namespace CrmArchetype\Archetype;

class CommunicationThread
{
    /** @var Communication[] */
    private array $communications = [];

    private \DateTimeImmutable $start;
    private ?\DateTimeImmutable $end = null;

    public function __construct(
        private readonly string $topicName,
        private readonly ?string $briefDescription = null,
    ) {
        $this->start = new \DateTimeImmutable();
    }

    public function addCommunication(Communication $communication): void
    {
        $this->communications[] = $communication;
    }

    /** @return Communication[] */
    public function communications(): array
    {
        return $this->communications;
    }

    public function topicName(): string
    {
        return $this->topicName;
    }

    public function briefDescription(): ?string
    {
        return $this->briefDescription;
    }

    public function close(): void
    {
        $this->end = new \DateTimeImmutable();
    }

    public function isClosed(): bool
    {
        return $this->end !== null;
    }
}
