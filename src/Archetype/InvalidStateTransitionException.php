<?php

declare(strict_types=1);

namespace CrmArchetype\Archetype;

final class InvalidStateTransitionException extends \DomainException
{
    public static function create(ActionState $from, ActionState $to): self
    {
        return new self(sprintf(
            'Cannot transition from "%s" to "%s".',
            $from->value,
            $to->value,
        ));
    }
}
