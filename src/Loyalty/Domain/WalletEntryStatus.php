<?php

declare(strict_types=1);

namespace Loyalty\Domain;

enum WalletEntryStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Debited = 'debited';
}
