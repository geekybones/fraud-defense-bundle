<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense;

use Symfony\Component\HttpFoundation\Request;

/** Input for building a Google reCAPTCHA Enterprise assessment event. */
final readonly class AssessmentEventContext
{
    public function __construct(
        public string $token,
        public string $siteKey,
        public string $action = 'default',
        public ?Request $request = null,
    ) {
    }
}
