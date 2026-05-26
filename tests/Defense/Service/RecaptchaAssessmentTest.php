<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense\Service;

use GeekyBones\FraudDefenseBundle\Assessment\AssessmentClientInterface;
use GeekyBones\FraudDefenseBundle\Assessment\Exception\AssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Event\RecaptchaAssessmentEvent;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\RecaptchaAssessmentMapper;
use GeekyBones\FraudDefenseBundle\Defense\Model\RecaptchaAssessmentResult;
use GeekyBones\FraudDefenseBundle\Defense\Service\RecaptchaAssessment;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use GeekyBones\FraudDefenseBundle\Http\ClientIpResolver;
use Google\ApiCore\ApiException;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\RiskAnalysis;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class RecaptchaAssessmentTest extends TestCase
{
    private MockObject&AssessmentClientInterface $client;

    private RecaptchaAssessment $assessment;

    protected function setUp(): void
    {
        $this->client = $this->createMock(AssessmentClientInterface::class);
        $this->assessment = new RecaptchaAssessment(
            new SiteKeyResolver('score-key', 'checkbox-key'),
            $this->client,
            new RecaptchaAssessmentEvent(new ClientIpResolver()),
            new RecaptchaAssessmentMapper(new AssessmentSerializer()),
            0.5,
        );
    }

    public function test_assess_returns_passed_result(): void
    {
        $this->client
            ->expects(self::once())
            ->method('create')
            ->willReturn($this->googleAssessment(tokenValid: true, score: 0.9, action: 'login'));

        $result = $this->assessment->assess('token-abc', 'login');

        self::assertTrue($result->hasPassed());
        self::assertEqualsWithDelta(0.9, $result->getScore(), 0.001);
        self::assertSame('login', $result->getAction());
    }

    public function test_assess_uses_checkbox_site_key(): void
    {
        $this->client
            ->expects(self::once())
            ->method('create')
            ->willReturn($this->googleAssessment(tokenValid: true, score: 1.0, action: 'default'));

        self::assertTrue($this->assessment->assess('token', type: 'checkbox')->hasPassed());
    }

    public function test_assess_fails_when_score_below_threshold(): void
    {
        $this->client->method('create')->willReturn($this->googleAssessment(
            tokenValid: true,
            score: 0.2,
            action: 'login',
            reasons: [1],
        ));

        $result = $this->assessment->assess('token', 'login');

        self::assertFalse($result->hasPassed());
        self::assertContains(1, $result->getReasons());
    }

    public function test_assess_wraps_assessment_exception_from_client(): void
    {
        $this->client
            ->method('create')
            ->willThrowException(new AssessmentException('reCAPTCHA token is invalid (EXPIRED).'));

        try {
            $this->assessment->assess('expired-token');
            self::fail('Expected DefenseAssessmentException');
        } catch (DefenseAssessmentException $e) {
            self::assertSame('reCAPTCHA token is invalid (EXPIRED).', $e->getMessage());
            self::assertInstanceOf(AssessmentException::class, $e->getPrevious());
        }
    }

    public function test_assess_propagates_assessment_exception(): void
    {
        $this->client
            ->method('create')
            ->willThrowException(new AssessmentException('Fraud Defense assessment failed: error'));

        try {
            $this->assessment->assess('token');
            self::fail('Expected DefenseAssessmentException');
        } catch (DefenseAssessmentException $e) {
            self::assertStringContainsString('Fraud Defense assessment failed', $e->getMessage());
            self::assertInstanceOf(AssessmentException::class, $e->getPrevious());
        }
    }

    public function test_recaptcha_assessment_result_model(): void
    {
        $result = new RecaptchaAssessmentResult(
            score: 0.9,
            reasons: [],
            tokenValid: true,
            action: 'login',
            passed: true,
        );

        self::assertTrue($result->hasPassed());
        self::assertSame(0.9, $result->getScore());
    }

    public function test_defense_assessment_exception_wraps_api_exception(): void
    {
        $apiException = $this->createStub(ApiException::class);
        $wrapped = new DefenseAssessmentException('reCAPTCHA Enterprise assessment failed: error', 0, $apiException);

        self::assertInstanceOf(DefenseAssessmentException::class, $wrapped);
        self::assertSame($apiException, $wrapped->getPrevious());
    }

    /**
     * @param list<int> $reasons
     */
    private function googleAssessment(
        bool $tokenValid,
        float $score,
        string $action,
        int $invalidReason = 0,
        array $reasons = [],
    ): Assessment {
        $tokenProperties = (new TokenProperties())
            ->setValid($tokenValid)
            ->setAction($action)
            ->setHostname('www.example.com')
            ->setInvalidReason($invalidReason);

        $riskAnalysis = (new RiskAnalysis())->setScore($score);
        if ([] !== $reasons) {
            $riskAnalysis->setReasons($reasons);
        }

        return (new Assessment())
            ->setTokenProperties($tokenProperties)
            ->setRiskAnalysis($riskAnalysis);
    }
}
