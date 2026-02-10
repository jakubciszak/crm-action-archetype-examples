<?php

declare(strict_types=1);

namespace App\SharedKernel\CrmArchetype\State;

/**
 * Bazowy status z archetypu CRM Activity: pending → open → closed.
 * Domeny rozszerzają ten minimalny zestaw o własne stany.
 */
enum ActionStatus: string
{
    case Pending = 'pending';
    case Open = 'open';
    case Closed = 'closed';
}
