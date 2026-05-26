<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Mapper;

use BackedEnum;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentPassEvaluator;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentSerializer;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentTokenProperties;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentResult;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use UnitEnum;

final class TransactionAssessmentMapper
{
    public function __construct(
        private readonly AssessmentSerializer $serializer,
    ) {
    }

    public function map(
        Assessment $assessment,
        string $expectedAction,
        float $maxTransactionRisk,
        ?string $expectedHostname = null,
        ?float $minScore = null,
    ): TransactionAssessmentResult {
        $token = AssessmentTokenProperties::from($assessment->getTokenProperties());

        $fraudPrevention = $assessment->getFraudPreventionAssessment();

        if (null === $fraudPrevention) {
            throw new DefenseAssessmentException('Transaction Defense assessment did not return fraudPreventionAssessment. In Google Cloud Console, complete all Transaction defense integration steps (send transaction data, interpret assessments, annotate events). On the server, include paymentMethod plus (cardBin and cardLastFour) or user accountId, email, or phoneNumber in TransactionAssessmentContext. Use the same GCP project and site key (matching form type) as in the console.');
        }

        $riskReasons = [];
        foreach ($fraudPrevention->getRiskReasons() as $reason) {
            if (\is_int($reason) || \is_string($reason)) {
                $riskReasons[] = $reason;
            } elseif ($reason instanceof BackedEnum) {
                $riskReasons[] = $reason->value;
            } elseif ($reason instanceof UnitEnum) {
                $riskReasons[] = $reason->name;
            }
        }

        $riskAnalysis = $assessment->getRiskAnalysis();
        $score = null !== $riskAnalysis ? $riskAnalysis->getScore() : null;
        $transactionRisk = $fraudPrevention->getTransactionRisk();

        $passed = AssessmentPassEvaluator::passesRiskBelow(
            $token->tokenValid,
            $transactionRisk,
            $maxTransactionRisk,
            $token->action,
            $expectedAction,
            $token->hostname,
            $expectedHostname,
        );

        if ($passed && null !== $minScore && null !== $score && $score < $minScore) {
            $passed = false;
        }

        return new TransactionAssessmentResult(
            transactionRisk: $transactionRisk,
            riskReasons: $riskReasons,
            tokenValid: $token->tokenValid,
            action: $token->action,
            passed: $passed,
            raw: $this->serializer->toArray($assessment),
            invalidReason: $token->invalidReason,
            score: $score,
        );
    }
}
