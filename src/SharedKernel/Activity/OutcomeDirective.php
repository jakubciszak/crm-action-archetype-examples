<?php

declare(strict_types=1);

namespace SharedKernel\Activity;

final readonly class OutcomeDirective
{
    private function __construct(
        public OutcomeDirectiveType $type,
        public array $params = [],
    ) {}

    public static function advance(): self
    {
        return new self(OutcomeDirectiveType::AdvanceStage);
    }

    public static function retry(string $stepCode): self
    {
        return new self(OutcomeDirectiveType::RetryStep, ['stepCode' => $stepCode]);
    }

    public static function spawnStep(string $stepCode, string $stageCode): self
    {
        return new self(OutcomeDirectiveType::SpawnStep, ['stepCode' => $stepCode, 'stageCode' => $stageCode]);
    }

    public static function complete(): self
    {
        return new self(OutcomeDirectiveType::CompleteProcess);
    }

    public static function fail(string $reason): self
    {
        return new self(OutcomeDirectiveType::FailProcess, ['reason' => $reason]);
    }

    public static function escalate(): self
    {
        return new self(OutcomeDirectiveType::Escalate);
    }

    public static function hold(string $reason): self
    {
        return new self(OutcomeDirectiveType::Hold, ['reason' => $reason]);
    }
}
