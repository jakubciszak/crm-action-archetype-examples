<?php

declare(strict_types=1);

namespace App\Loyalty\Domain\Model;

use App\SharedKernel\CrmArchetype\Model\Outcome;
use App\SharedKernel\CrmArchetype\Model\PartySignature;

/**
 * IncentiveDecision — Outcome w domenie loyalty.
 *
 * Outcome to IncentiveDecision z konkretnymi efektami biznesowymi:
 * - journalEntries — zapis księgowy
 * - rewardGrants — przyznane nagrody
 *
 * W loyalty Outcome EMITUJE efekty (nie steruje sekwencją jak w onboardingu).
 */
final class IncentiveDecision
{
    public const POINTS_GRANTED = 'points_granted';
    public const REWARD_GRANT = 'reward_grant';
    public const REJECTED = 'rejected';

    public static function pointsGranted(
        int $points,
        string $memberId,
        string $description = 'Punkty za aktywność',
        ?PartySignature $approver = null,
    ): Outcome {
        return new Outcome(
            self::POINTS_GRANTED,
            $description,
            ['points' => $points, 'member_id' => $memberId],
            $approver,
        );
    }

    public static function rewardGrant(
        string $rewardCode,
        string $memberId,
        string $description = 'Nagroda za aktywność',
        ?PartySignature $approver = null,
    ): Outcome {
        return new Outcome(
            self::REWARD_GRANT,
            $description,
            ['reward_code' => $rewardCode, 'member_id' => $memberId],
            $approver,
        );
    }

    public static function rejected(
        string $reason,
        ?PartySignature $approver = null,
    ): Outcome {
        return new Outcome(
            self::REJECTED,
            'Odrzucone',
            ['reason' => $reason],
            $approver,
        );
    }

    /**
     * @return Outcome[] Standardowe possibleOutcomes dla incentive actions
     */
    public static function standardPossibleOutcomes(): array
    {
        return [
            new Outcome(self::POINTS_GRANTED, 'Przyznanie punktów'),
            new Outcome(self::REWARD_GRANT, 'Przyznanie nagrody'),
            new Outcome(self::REJECTED, 'Odrzucone'),
        ];
    }
}
