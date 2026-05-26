<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense;

final class AssessmentPassEvaluator
{
    public static function passesScore(
        bool $tokenValid,
        float $score,
        float $minScore,
        string $responseAction,
        string $expectedAction,
        string $responseHostname = '',
        ?string $expectedHostname = null,
    ): bool {
        if (!self::passesCommon($tokenValid, $responseAction, $expectedAction, $responseHostname, $expectedHostname)) {
            return false;
        }

        return $score >= $minScore;
    }

    public static function passesRiskBelow(
        bool $tokenValid,
        float $risk,
        float $maxRisk,
        string $responseAction,
        string $expectedAction,
        string $responseHostname = '',
        ?string $expectedHostname = null,
    ): bool {
        if (!self::passesCommon($tokenValid, $responseAction, $expectedAction, $responseHostname, $expectedHostname)) {
            return false;
        }

        return $risk < $maxRisk;
    }

    private static function passesCommon(
        bool $tokenValid,
        string $responseAction,
        string $expectedAction,
        string $responseHostname,
        ?string $expectedHostname,
    ): bool {
        if (!$tokenValid) {
            return false;
        }

        if ('' !== $expectedAction && $responseAction !== $expectedAction) {
            return false;
        }

        if (null !== $expectedHostname && '' !== $expectedHostname && $responseHostname !== $expectedHostname) {
            return false;
        }

        return true;
    }
}
