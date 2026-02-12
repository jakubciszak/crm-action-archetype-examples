<?php

declare(strict_types=1);

namespace Loyalty\Application;

final readonly class ReverseActionCommand
{
    public function __construct(
        public string $campaignId,
        public string $actionId,
        public string $reason,
    ) {}
}
