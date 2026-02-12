<?php

declare(strict_types=1);

namespace Loyalty\Application;

final readonly class RecordActionCommand
{
    public function __construct(
        public string $actionId,
        public string $actionType,
        public array $payload,
        public string $participantId,
        public string $campaignId,
    ) {}
}
