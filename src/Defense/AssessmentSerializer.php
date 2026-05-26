<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense;

use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Psr\Log\LoggerInterface;
use Throwable;

final class AssessmentSerializer
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Assessment $assessment): array
    {
        try {
            return json_decode((string) $assessment->serializeToJsonString(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->logger?->warning('Failed to serialize reCAPTCHA Enterprise assessment.', ['exception' => $e]);

            return [];
        }
    }
}
