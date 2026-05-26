<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final class TransactionAssessmentResult
{
    /**
     * @param list<int|string>     $riskReasons
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private readonly float $transactionRisk,
        private readonly array $riskReasons,
        private readonly bool $tokenValid,
        private readonly string $action,
        private readonly bool $passed,
        private readonly array $raw = [],
        private readonly int $invalidReason = 0,
        private readonly ?float $score = null,
    ) {
    }

    public function getTransactionRisk(): float
    {
        return $this->transactionRisk;
    }

    /**
     * @return list<int|string>
     */
    public function getRiskReasons(): array
    {
        return $this->riskReasons;
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

    public function getScore(): ?float
    {
        return $this->score;
    }
}
