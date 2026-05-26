<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Contract;

use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentResult;

interface TransactionAssessmentInterface
{
    /**
     * Assess a payment transaction (frontend integration: pass reCAPTCHA token from execute()).
     *
     * @param string $token  reCAPTCHA Enterprise token from the checkout page
     * @param string $action Expected action (e.g. purchase); pass '' to skip action check
     */
    public function assess(
        string $token,
        string $action,
        TransactionAssessmentContext $context,
        ?float $maxTransactionRisk = null,
        ?float $minScore = null,
        string $type = 'score',
    ): TransactionAssessmentResult;
}
