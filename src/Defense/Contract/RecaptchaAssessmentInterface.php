<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Contract;

use GeekyBones\FraudDefenseBundle\Defense\Model\RecaptchaAssessmentResult;

interface RecaptchaAssessmentInterface
{
    /**
     * @param string     $token    Token from grecaptcha.enterprise
     * @param string     $action   Expected action; pass '' to skip verification
     * @param float|null $minScore Minimum score (0.0–1.0); null uses bundle default
     * @param string     $type     Widget type: score or checkbox
     */
    public function assess(string $token, string $action = 'default', ?float $minScore = null, string $type = 'score'): RecaptchaAssessmentResult;
}
