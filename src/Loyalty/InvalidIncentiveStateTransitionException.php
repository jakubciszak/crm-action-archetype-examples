<?php

declare(strict_types=1);

namespace CrmArchetype\Loyalty;

final class InvalidIncentiveStateTransitionException extends \DomainException
{
    public static function create(IncentiveActionState $from, IncentiveActionState $to): self
    {
        return new self(sprintf(
            'Cannot transition from "%s" to "%s".',
            $from->value,
            $to->value,
        ));
    }
}
