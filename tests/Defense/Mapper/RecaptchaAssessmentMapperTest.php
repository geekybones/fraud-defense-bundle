<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense\Mapper;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\RecaptchaAssessmentMapper;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\RiskAnalysis;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties;
use PHPUnit\Framework\TestCase;

final class RecaptchaAssessmentMapperTest extends TestCase
{
    private RecaptchaAssessmentMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new RecaptchaAssessmentMapper(new AssessmentSerializer());
    }

    public function test_maps_score_and_passes_above_threshold(): void
    {
        $assessment = (new Assessment())
            ->setTokenProperties((new TokenProperties())->setValid(true)->setAction('login'))
            ->setRiskAnalysis((new RiskAnalysis())->setScore(0.9));

        $result = $this->mapper->map($assessment, 'login', 0.5);

        self::assertTrue($result->hasPassed());
        self::assertEqualsWithDelta(0.9, $result->getScore(), 0.001);
        self::assertSame('login', $result->getAction());
        self::assertTrue($result->isTokenValid());
    }

    public function test_fails_when_score_below_threshold(): void
    {
        $assessment = (new Assessment())
            ->setTokenProperties((new TokenProperties())->setValid(true)->setAction('login'))
            ->setRiskAnalysis((new RiskAnalysis())->setScore(0.3));

        $result = $this->mapper->map($assessment, 'login', 0.5);

        self::assertFalse($result->hasPassed());
    }

    public function test_fails_when_action_mismatch(): void
    {
        $assessment = (new Assessment())
            ->setTokenProperties((new TokenProperties())->setValid(true)->setAction('signup'))
            ->setRiskAnalysis((new RiskAnalysis())->setScore(0.9));

        $result = $this->mapper->map($assessment, 'login', 0.5);

        self::assertFalse($result->hasPassed());
    }

    public function test_fails_when_token_invalid(): void
    {
        $assessment = (new Assessment())
            ->setTokenProperties((new TokenProperties())->setValid(false)->setAction('login'))
            ->setRiskAnalysis((new RiskAnalysis())->setScore(0.9));

        $result = $this->mapper->map($assessment, 'login', 0.5);

        self::assertFalse($result->hasPassed());
    }

    public function test_populates_raw_from_serializer(): void
    {
        $assessment = (new Assessment())
            ->setTokenProperties((new TokenProperties())->setValid(true)->setAction('login'))
            ->setRiskAnalysis((new RiskAnalysis())->setScore(0.7));

        $result = $this->mapper->map($assessment, 'login', 0.5);

        self::assertNotEmpty($result->getRaw());
        self::assertArrayHasKey('tokenProperties', $result->getRaw());
        self::assertArrayHasKey('riskAnalysis', $result->getRaw());
    }

    public function test_throws_when_risk_analysis_missing(): void
    {
        $this->expectException(DefenseAssessmentException::class);
        $this->expectExceptionMessage('incomplete response');

        $this->mapper->map(
            (new Assessment())->setTokenProperties((new TokenProperties())->setValid(true)->setAction('login')),
            'login',
            0.5,
        );
    }
}
