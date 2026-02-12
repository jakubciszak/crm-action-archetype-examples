<?php

declare(strict_types=1);

namespace Loyalty\Domain;

final class LoyaltyCampaign
{
    /** @var ActivityStream[] */
    private array $streams = [];

    public function __construct(
        private readonly string $id,
        private readonly string $name,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function addStream(ActivityStream $stream): void
    {
        $this->streams[] = $stream;
    }

    /** @return ActivityStream[] */
    public function streams(): array
    {
        return $this->streams;
    }

    public function findStream(string $activityType): ?ActivityStream
    {
        foreach ($this->streams as $stream) {
            if ($stream->activityType() === $activityType) {
                return $stream;
            }
        }
        return null;
    }

    public function recordAction(IncentiveAction $action): void
    {
        $stream = $this->findStream($action->actionType());
        if ($stream === null) {
            $stream = new ActivityStream($action->actionType());
            $this->addStream($stream);
        }
        $stream->addAction($action);
    }
}
