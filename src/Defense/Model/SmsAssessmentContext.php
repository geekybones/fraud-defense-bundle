<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final class SmsAssessmentContext
{
    public function __construct(
        public readonly string $phoneNumber,
        public readonly string $accountId = '',
    ) {
    }
}
