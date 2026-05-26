<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Mapper;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentPassEvaluator;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentTokenProperties;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\RecaptchaAssessmentResult;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;

final class RecaptchaAssessmentMapper
{
    public function __construct(
        private readonly AssessmentSerializer $serializer,
    ) {
    }

    public function map(
        Assessment $assessment,
        string $expectedAction,
        float $minScore,
        ?string $expectedHostname = null,
    ): RecaptchaAssessmentResult {
        $riskAnalysis = $assessment->getRiskAnalysis();

        if (null === $riskAnalysis) {
            throw new DefenseAssessmentException('reCAPTCHA Enterprise assessment returned an incomplete response.');
        }

        $token = AssessmentTokenProperties::from($assessment->getTokenProperties());

        $reasons = array_values([...$riskAnalysis->getReasons()]);
        $score = $riskAnalysis->getScore();

        return new RecaptchaAssessmentResult(
            score: $score,
            reasons: $reasons,
            tokenValid: $token->tokenValid,
            action: $token->action,
            passed: AssessmentPassEvaluator::passesScore(
                $token->tokenValid,
                $score,
                $minScore,
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
