<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Assessment;

use GeekyBones\FraudDefenseBundle\Assessment\Exception\AssessmentException;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;
use UnexpectedValueException;

/**
 * Rejects Google assessments whose tokenProperties mark the submitted token invalid.
 */
final class AssessmentTokenValidator
{
    public static function assertValidWhenTokenSent(Event $event, Assessment $assessment): void
    {
        if ('' === $event->getToken()) {
            return;
        }

        $tokenProperties = $assessment->getTokenProperties();

        if (null === $tokenProperties) {
            throw new AssessmentException('reCAPTCHA Enterprise assessment returned no tokenProperties for a submitted token.');
        }

        if ($tokenProperties->getValid()) {
            return;
        }

        $reasonLabel = self::invalidReasonLabel($tokenProperties->getInvalidReason());

        throw new AssessmentException('' !== $reasonLabel ? \sprintf('reCAPTCHA token is invalid (%s).', $reasonLabel) : 'reCAPTCHA token is invalid.');
    }

    private static function invalidReasonLabel(int $invalidReason): string
    {
        if (InvalidReason::INVALID_REASON_UNSPECIFIED === $invalidReason) {
            return '';
        }

        try {
            return InvalidReason::name($invalidReason);
        } catch (UnexpectedValueException) {
            return 'UNKNOWN_INVALID_REASON';
        }
    }
}
