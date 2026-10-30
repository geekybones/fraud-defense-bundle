<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense;

use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties;

/** Normalized token fields from a Google assessment response. */
final class AssessmentTokenProperties
{
    public function __construct(
        public readonly bool $tokenValid,
        public readonly string $action,
        public readonly string $hostname,
        public readonly int $invalidReason,
    ) {
    }

    public static function from(?TokenProperties $tokenProperties): self
    {
        if (!$tokenProperties instanceof TokenProperties) {
            return new self(false, '', '', 0);
        }

        return new self(
            $tokenProperties->getValid(),
            $tokenProperties->getAction(),
            $tokenProperties->getHostname(),
            $tokenProperties->getInvalidReason(),
        );
    }
}
