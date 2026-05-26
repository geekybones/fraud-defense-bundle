<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final readonly class SmsAssessmentContext
{
    public function __construct(
        public string $phoneNumber,
        public string $accountId = '',
    ) {
    }
}
