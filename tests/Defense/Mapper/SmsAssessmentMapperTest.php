<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense\Mapper;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\SmsAssessmentMapper;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\PhoneFraudAssessment;
use Google\Cloud\RecaptchaEnterprise\V1\SmsTollFraudVerdict;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties;
use PHPUnit\Framework\TestCase;

final class SmsAssessmentMapperTest extends TestCase
{
    private SmsAssessmentMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SmsAssessmentMapper(new AssessmentSerializer());
    }

    public function test_maps_sms_verdict(): void
    {
        $assessment = (new Assessment())
            ->setTokenProperties((new TokenProperties())->setValid(true)->setAction('sms_otp'))
            ->setPhoneFraudAssessment(
                (new PhoneFraudAssessment())->setSmsTollFraudVerdict(
                    (new SmsTollFraudVerdict())->setRisk(0.2),
                ),
            );

        $result = $this->mapper->map($assessment, 'sms_otp', 0.5);

        self::assertTrue($result->hasPassed());
        self::assertEqualsWithDelta(0.2, $result->getSmsRisk(), 0.001);
    }

    public function test_throws_when_phone_fraud_assessment_missing(): void
    {
        $this->expectException(DefenseAssessmentException::class);
        $this->expectExceptionMessage('phoneFraudAssessment');

        $this->mapper->map(
            (new Assessment())->setTokenProperties((new TokenProperties())->setValid(true)),
            'sms_otp',
            0.5,
        );
    }
}
