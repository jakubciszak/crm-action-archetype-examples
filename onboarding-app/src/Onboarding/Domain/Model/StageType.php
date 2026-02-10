<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Model;

enum StageType: string
{
    case KYC = 'kyc';
    case Contract = 'contract';
    case Setup = 'setup';
    case Training = 'training';
}
