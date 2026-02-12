<?php

declare(strict_types=1);

namespace Loyalty\Domain;

interface IncentiveRule
{
    public function supports(string $actionType): bool;

    public function evaluate(IncentiveAction $action): IncentiveDecision;
}
