<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense;

use Symfony\Component\HttpFoundation\Request;

/** Input for building a Google reCAPTCHA Enterprise assessment event. */
final class AssessmentEventContext
{
    public function __construct(
        public readonly string $token,
        public readonly string $siteKey,
        public readonly string $action = 'default',
        public readonly ?Request $request = null,
    ) {
    }
}
