<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Mapper;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentPassEvaluator;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentTokenProperties;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentResult;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;

final class SmsAssessmentMapper
{
    public function __construct(
        private readonly AssessmentSerializer $serializer,
    ) {
    }

    public function map(
        Assessment $assessment,
        string $expectedAction,
        float $maxSmsRisk,
        ?string $expectedHostname = null,
    ): SmsAssessmentResult {
        $token = AssessmentTokenProperties::from($assessment->getTokenProperties());

        $phoneFraud = $assessment->getPhoneFraudAssessment();

        if (null === $phoneFraud || !$phoneFraud->hasSmsTollFraudVerdict()) {
            throw new DefenseAssessmentException('SMS Defense assessment did not return phoneFraudAssessment. Ensure SMS toll fraud protection is enabled and a phone number was provided.');
        }

        $verdict = $phoneFraud->getSmsTollFraudVerdict();
        if (null === $verdict) {
            throw new DefenseAssessmentException('SMS Defense assessment did not return smsTollFraudVerdict.');
        }

        $reasons = array_values([...$verdict->getReasons()]);
        $smsRisk = $verdict->getRisk();

        return new SmsAssessmentResult(
            smsRisk: $smsRisk,
            reasons: $reasons,
            tokenValid: $token->tokenValid,
            action: $token->action,
            passed: AssessmentPassEvaluator::passesRiskBelow(
                $token->tokenValid,
                $smsRisk,
                $maxSmsRisk,
                $token->action,
                $expectedAction,
                $token->hostname,
                $expectedHostname,
            ),
            raw: $this->serializer->toArray($assessment),
            invalidReason: $token->invalidReason,
        );
    }
}
