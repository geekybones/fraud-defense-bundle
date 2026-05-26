<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense\Service;

use GeekyBones\FraudDefenseBundle\Assessment\AssessmentClientInterface;
use GeekyBones\FraudDefenseBundle\Assessment\Exception\AssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Event\RecaptchaAssessmentEvent;
use GeekyBones\FraudDefenseBundle\Defense\Event\TransactionAssessmentEvent;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\GoogleTransactionDataMapper;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\TransactionAssessmentMapper;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Service\TransactionAssessment;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use GeekyBones\FraudDefenseBundle\Http\ClientIpResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class TransactionAssessmentTest extends TestCase
{
    private MockObject&AssessmentClientInterface $client;

    private TransactionAssessment $assessment;

    protected function setUp(): void
    {
        $this->client = $this->createMock(AssessmentClientInterface::class);
        $this->assessment = new TransactionAssessment(
            new SiteKeyResolver('score-key', 'checkbox-key'),
            $this->client,
            new TransactionAssessmentEvent(
                new RecaptchaAssessmentEvent(new ClientIpResolver()),
                new GoogleTransactionDataMapper(),
            ),
            new TransactionAssessmentMapper(new AssessmentSerializer()),
            0.7,
        );
    }

    public function test_assess_wraps_assessment_exception(): void
    {
        $this->client
            ->method('create')
            ->willThrowException(new AssessmentException('reCAPTCHA token is invalid (EXPIRED).'));

        $this->expectException(DefenseAssessmentException::class);
        $this->expectExceptionMessage('reCAPTCHA token is invalid (EXPIRED).');

        $this->assessment->assess(
            'token',
            'purchase',
            new TransactionAssessmentContext(
                paymentMethod: 'credit-card',
                cardBin: '411111',
                cardLastFour: '1234',
            ),
        );
    }
}
