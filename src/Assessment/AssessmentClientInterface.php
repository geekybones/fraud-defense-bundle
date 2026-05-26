<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Assessment;

use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Event;

interface AssessmentClientInterface
{
    public function create(Event $event): Assessment;
}
