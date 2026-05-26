<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentPassEvaluator;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssessmentPassEvaluatorTest extends TestCase
{
    #[DataProvider('scorePassScenariosProvider')]
    public function test_passes_score(
        bool $tokenValid,
        float $score,
        string $responseAction,
        string $expectedAction,
        float $minScore,
        bool $expected,
        string $responseHostname = 'www.example.com',
        ?string $expectedHostname = null,
    ): void {
        self::assertSame(
            $expected,
            AssessmentPassEvaluator::passesScore(
                $tokenValid,
                $score,
                $minScore,
                $responseAction,
                $expectedAction,
                $responseHostname,
                $expectedHostname,
            ),
        );
    }

    #[DataProvider('riskPassScenariosProvider')]
    public function test_passes_risk_below(
        bool $tokenValid,
        float $risk,
        float $maxRisk,
        bool $expected,
    ): void {
        self::assertSame(
            $expected,
            AssessmentPassEvaluator::passesRiskBelow(
                $tokenValid,
                $risk,
                $maxRisk,
                'purchase',
                'purchase',
            ),
        );
    }

    public static function scorePassScenariosProvider(): Generator
    {
        yield 'valid token and score above threshold' => [true, 0.9, 'login', 'login', 0.5, true];
        yield 'valid token but score below threshold' => [true, 0.3, 'login', 'login', 0.5, false];
        yield 'invalid token' => [false, 0.9, 'login', 'login', 0.5, false];
        yield 'action mismatch' => [true, 0.9, 'signup', 'login', 0.5, false];
        yield 'empty expected action skips action check' => [true, 0.9, 'anything', '', 0.5, true];
        yield 'score exactly at threshold' => [true, 0.5, 'login', 'login', 0.5, true];
        yield 'hostname mismatch' => [true, 0.9, 'login', 'login', 0.5, false, 'evil.com', 'www.example.com'];
        yield 'hostname match' => [true, 0.9, 'login', 'login', 0.5, true, 'www.example.com', 'www.example.com'];
    }

    public static function riskPassScenariosProvider(): Generator
    {
        yield 'low risk passes' => [true, 0.3, 0.7, true];
        yield 'high risk fails' => [true, 0.9, 0.7, false];
        yield 'risk at threshold fails' => [true, 0.7, 0.7, false];
        yield 'invalid token fails' => [false, 0.1, 0.7, false];
    }
}
