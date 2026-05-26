<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Contract;

use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentResult;

interface SmsAssessmentInterface
{
    /**
     * Assess an SMS OTP request for toll fraud risk.
     *
     * @param string $token  reCAPTCHA Enterprise token from execute()
     * @param string $action Expected action (e.g. sms_otp); pass '' to skip action check
     */
    public function assess(
        string $token,
        string $action,
        SmsAssessmentContext $context,
        ?float $maxSmsRisk = null,
        string $type = 'score',
    ): SmsAssessmentResult;
}
