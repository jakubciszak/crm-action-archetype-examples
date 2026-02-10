<?php

declare(strict_types=1);

namespace App\Onboarding\Domain\Model;

use App\SharedKernel\CrmArchetype\Model\Outcome;
use App\SharedKernel\CrmArchetype\Model\PartySignature;

/**
 * StepResult — Outcome w domenie onboardingu.
 *
 * Trzy ścieżki:
 * - Accepted      → następna faza
 * - NeedsSupplement → pętla zwrotna, nowa Communication/Action w tym samym Stage
 * - Rejected      → zamknij Case
 */
final class StepResult
{
    public const ACCEPTED = 'accepted';
    public const NEEDS_SUPPLEMENT = 'needs_supplement';
    public const REJECTED = 'rejected';

    public static function accepted(string $description = 'Zaakceptowane', ?PartySignature $approver = null): Outcome
    {
        return new Outcome(self::ACCEPTED, $description, [], $approver);
    }

    public static function needsSupplement(string $reason, ?PartySignature $approver = null): Outcome
    {
        return new Outcome(self::NEEDS_SUPPLEMENT, 'Wymagane uzupełnienie', ['reason' => $reason], $approver);
    }

    public static function rejected(string $reason, ?PartySignature $approver = null): Outcome
    {
        return new Outcome(self::REJECTED, 'Odrzucone', ['reason' => $reason], $approver);
    }

    /**
     * @return Outcome[] Standardowe possibleOutcomes dla kroków onboardingowych
     */
    public static function standardPossibleOutcomes(): array
    {
        return [
            new Outcome(self::ACCEPTED, 'Zaakceptowane'),
            new Outcome(self::NEEDS_SUPPLEMENT, 'Wymagane uzupełnienie'),
            new Outcome(self::REJECTED, 'Odrzucone'),
        ];
    }
}
