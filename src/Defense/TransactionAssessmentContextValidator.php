<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense;

use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionUser;

final class TransactionAssessmentContextValidator
{
    public static function assertMeetsMinimumRequirements(TransactionAssessmentContext $context): void
    {
        if (self::hasCardDetails($context) || self::hasUserIdentifier($context->user)) {
            return;
        }

        throw new DefenseAssessmentException('TransactionAssessmentContext is incomplete for Transaction Defense. Google requires paymentMethod plus (cardBin and cardLastFour) or at least one of user accountId, email, or phoneNumber. See https://cloud.google.com/recaptcha/docs/fraud-prevention');
    }

    private static function hasCardDetails(TransactionAssessmentContext $context): bool
    {
        return '' !== $context->cardBin && '' !== $context->cardLastFour;
    }

    private static function hasUserIdentifier(?TransactionUser $user): bool
    {
        if (!$user instanceof TransactionUser) {
            return false;
        }

        return '' !== $user->accountId || '' !== $user->email || '' !== $user->phoneNumber;
    }
}
