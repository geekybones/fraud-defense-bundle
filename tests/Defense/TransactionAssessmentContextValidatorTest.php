<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense;

use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionUser;
use GeekyBones\FraudDefenseBundle\Defense\TransactionAssessmentContextValidator;
use PHPUnit\Framework\TestCase;

final class TransactionAssessmentContextValidatorTest extends TestCase
{
    public function test_accepts_card_details(): void
    {
        $this->expectNotToPerformAssertions();

        TransactionAssessmentContextValidator::assertMeetsMinimumRequirements(new TransactionAssessmentContext(
            paymentMethod: 'credit-card',
            cardBin: '411111',
            cardLastFour: '1234',
        ));
    }

    public function test_accepts_user_identifier(): void
    {
        $this->expectNotToPerformAssertions();

        TransactionAssessmentContextValidator::assertMeetsMinimumRequirements(new TransactionAssessmentContext(
            paymentMethod: 'credit-card',
            user: new TransactionUser(email: 'buyer@example.com'),
        ));
    }

    public function test_rejects_incomplete_data(): void
    {
        $this->expectException(DefenseAssessmentException::class);
        $this->expectExceptionMessage('TransactionAssessmentContext is incomplete');

        TransactionAssessmentContextValidator::assertMeetsMinimumRequirements(new TransactionAssessmentContext(
            paymentMethod: 'credit-card',
            value: 10.0,
        ));
    }
}
