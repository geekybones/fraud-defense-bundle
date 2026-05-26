<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense\Mapper;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\TransactionAssessmentMapper;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\FraudPreventionAssessment;
use Google\Cloud\RecaptchaEnterprise\V1\RiskAnalysis;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties;
use PHPUnit\Framework\TestCase;

final class TransactionAssessmentMapperTest extends TestCase
{
    private TransactionAssessmentMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TransactionAssessmentMapper(new AssessmentSerializer());
    }

    private function buildAssessment(float $transactionRisk, bool $tokenValid = true, ?float $score = null): Assessment
    {
        $assessment = (new Assessment())
            ->setTokenProperties((new TokenProperties())->setValid($tokenValid)->setAction('purchase'))
            ->setFraudPreventionAssessment((new FraudPreventionAssessment())->setTransactionRisk($transactionRisk));

        if (null !== $score) {
            $assessment->setRiskAnalysis((new RiskAnalysis())->setScore($score));
        }

        return $assessment;
    }

    public function test_maps_transaction_risk_and_passes_below_threshold(): void
    {
        $result = $this->mapper->map($this->buildAssessment(0.2), 'purchase', 0.5);

        self::assertTrue($result->hasPassed());
        self::assertEqualsWithDelta(0.2, $result->getTransactionRisk(), 0.001);
        self::assertSame('purchase', $result->getAction());
        self::assertTrue($result->isTokenValid());
    }

    public function test_fails_when_transaction_risk_above_threshold(): void
    {
        $result = $this->mapper->map($this->buildAssessment(0.8), 'purchase', 0.5);

        self::assertFalse($result->hasPassed());
    }

    public function test_min_score_overlay_fails_when_score_too_low(): void
    {
        $result = $this->mapper->map($this->buildAssessment(0.2, score: 0.4), 'purchase', 0.5, minScore: 0.7);

        self::assertFalse($result->hasPassed());
        self::assertEqualsWithDelta(0.4, $result->getScore(), 0.001);
    }

    public function test_min_score_overlay_passes_when_score_sufficient(): void
    {
        $result = $this->mapper->map($this->buildAssessment(0.2, score: 0.9), 'purchase', 0.5, minScore: 0.7);

        self::assertTrue($result->hasPassed());
    }

    public function test_min_score_ignored_when_risk_already_fails(): void
    {
        $result = $this->mapper->map($this->buildAssessment(0.9, score: 0.95), 'purchase', 0.5, minScore: 0.7);

        self::assertFalse($result->hasPassed());
    }

    public function test_fails_when_action_mismatch(): void
    {
        $result = $this->mapper->map($this->buildAssessment(0.2), 'signup', 0.5);

        self::assertFalse($result->hasPassed());
    }

    public function test_fails_when_token_invalid(): void
    {
        $result = $this->mapper->map($this->buildAssessment(0.2, tokenValid: false), 'purchase', 0.5);

        self::assertFalse($result->hasPassed());
    }

    public function test_populates_raw_from_serializer(): void
    {
        $result = $this->mapper->map($this->buildAssessment(0.2), 'purchase', 0.5);

        self::assertNotEmpty($result->getRaw());
        self::assertArrayHasKey('tokenProperties', $result->getRaw());
    }

    public function test_throws_when_fraud_prevention_assessment_missing(): void
    {
        $this->expectException(DefenseAssessmentException::class);
        $this->expectExceptionMessage('fraudPreventionAssessment');

        $this->mapper->map(
            (new Assessment())->setTokenProperties((new TokenProperties())->setValid(true)->setAction('purchase')),
            'purchase',
            0.5,
        );
    }

    public function test_score_is_null_when_risk_analysis_absent(): void
    {
        $result = $this->mapper->map($this->buildAssessment(0.2), 'purchase', 0.5);

        self::assertNull($result->getScore());
    }
}
