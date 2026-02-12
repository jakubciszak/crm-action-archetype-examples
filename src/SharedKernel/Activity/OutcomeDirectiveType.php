<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

enum OutcomeDirectiveType: string
{
    case AdvanceStage = 'advance_stage';
    case RetryStep = 'retry_step';
    case SpawnStep = 'spawn_step';
    case CompleteProcess = 'complete_process';
    case FailProcess = 'fail_process';
    case Escalate = 'escalate';
    case Hold = 'hold';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::FailProcess, self::Escalate, self::Hold => true,
            default => false,
        };
    }

    public function isComposable(): bool
    {
        return $this === self::SpawnStep;
    }

    public function priority(): int
    {
        return match ($this) {
            self::FailProcess => 1,
            self::Escalate => 2,
            self::Hold => 3,
            self::RetryStep => 4,
            self::AdvanceStage => 5,
            self::SpawnStep => 6,
            self::CompleteProcess => 7,
        };
    }
}
