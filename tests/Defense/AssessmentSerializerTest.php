<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\RiskAnalysis;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
final class AssessmentSerializerTest extends TestCase
{
    public function test_serializes_assessment_to_array(): void
    {
        $assessment = (new Assessment())
            ->setTokenProperties((new TokenProperties())->setValid(true)->setAction('login'))
            ->setRiskAnalysis((new RiskAnalysis())->setScore(0.9));

        $result = (new AssessmentSerializer())->toArray($assessment);

        self::assertArrayHasKey('tokenProperties', $result);
        self::assertArrayHasKey('riskAnalysis', $result);
    }

    public function test_returns_empty_array_and_logs_warning_on_failure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to serialize reCAPTCHA Enterprise assessment.',
                self::arrayHasKey('exception'),
            );

        $broken = $this->createMock(Assessment::class);
        $broken->method('serializeToJsonString')->willThrowException(new RuntimeException('proto error'));

        $result = (new AssessmentSerializer($logger))->toArray($broken);

        self::assertSame([], $result);
    }

    public function test_returns_empty_array_silently_without_logger_on_failure(): void
    {
        $broken = $this->createMock(Assessment::class);
        $broken->method('serializeToJsonString')->willThrowException(new RuntimeException('proto error'));

        $result = (new AssessmentSerializer())->toArray($broken);

        self::assertSame([], $result);
    }
}
