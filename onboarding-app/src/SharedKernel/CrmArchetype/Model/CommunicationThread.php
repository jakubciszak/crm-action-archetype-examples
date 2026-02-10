<?php

declare(strict_types=1);

namespace App\SharedKernel\CrmArchetype\Model;

/**
 * CommunicationThread — faza/etap procesu.
 * Grupuje powiązane Communication w ramach jednego Case.
 */
class CommunicationThread
{
    /** @var Communication[] */
    protected array $communications = [];

    public function __construct(
        protected readonly string $id,
        protected readonly string $name,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
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
}
