<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final class SmsAssessmentResult
{
    /**
     * @param list<int|string>     $reasons
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private readonly float $smsRisk,
        private readonly array $reasons,
        private readonly bool $tokenValid,
        private readonly string $action,
        private readonly bool $passed,
        private readonly array $raw = [],
        private readonly int $invalidReason = 0,
    ) {
    }

    public function getSmsRisk(): float
    {
        return $this->smsRisk;
    }

    /**
     * @return list<int|string>
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    public function isTokenValid(): bool
    {
        return $this->tokenValid;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function hasPassed(): bool
    {
        return $this->passed;
    }

    public function getInvalidReason(): int
    {
        return $this->invalidReason;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}
