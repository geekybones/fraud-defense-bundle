<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense\Service;

use GeekyBones\FraudDefenseBundle\Assessment\AssessmentClientInterface;
use GeekyBones\FraudDefenseBundle\Assessment\Exception\AssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Event\RecaptchaAssessmentEvent;
use GeekyBones\FraudDefenseBundle\Defense\Event\SmsAssessmentEvent;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\SmsAssessmentMapper;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Service\SmsAssessment;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use GeekyBones\FraudDefenseBundle\Http\ClientIpResolver;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\PhoneFraudAssessment;
use Google\Cloud\RecaptchaEnterprise\V1\SmsTollFraudVerdict;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SmsAssessmentTest extends TestCase
{
    private MockObject&AssessmentClientInterface $client;

    private SmsAssessment $assessment;

    protected function setUp(): void
    {
        $this->client = $this->createMock(AssessmentClientInterface::class);
        $this->assessment = new SmsAssessment(
            new SiteKeyResolver('score-key', 'checkbox-key'),
            $this->client,
            new SmsAssessmentEvent(new RecaptchaAssessmentEvent(new ClientIpResolver())),
            new SmsAssessmentMapper(new AssessmentSerializer()),
            0.5,
        );
    }

    public function test_assess_returns_passed_result(): void
    {
        $this->client->method('create')->willReturn(
            (new Assessment())
                ->setTokenProperties((new TokenProperties())->setValid(true)->setAction('sms_otp'))
                ->setPhoneFraudAssessment(
                    (new PhoneFraudAssessment())->setSmsTollFraudVerdict(
                        (new SmsTollFraudVerdict())->setRisk(0.1),
                    ),
                ),
        );

        $result = $this->assessment->assess(
            'token',
            'sms_otp',
            new SmsAssessmentContext(phoneNumber: '+18005550175', accountId: 'user-1'),
        );

        self::assertTrue($result->hasPassed());
    }

    public function test_assess_wraps_assessment_exception(): void
    {
        $this->client
            ->method('create')
            ->willThrowException(new AssessmentException('SMS API down'));

        $this->expectException(DefenseAssessmentException::class);
        $this->expectExceptionMessage('SMS API down');

        $this->assessment->assess(
            'token',
            'sms_otp',
            new SmsAssessmentContext(phoneNumber: '+18005550175'),
        );
    }
}
