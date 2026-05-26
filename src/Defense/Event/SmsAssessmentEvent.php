<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Event;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentEventContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentContext;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\UserId;
use Google\Cloud\RecaptchaEnterprise\V1\UserInfo;

final class SmsAssessmentEvent
{
    public function __construct(
        private readonly RecaptchaAssessmentEvent $recaptchaAssessmentEvent,
    ) {
    }

    public function build(AssessmentEventContext $context, SmsAssessmentContext $sms): Event
    {
        $event = $this->recaptchaAssessmentEvent->build($context);

        $userInfo = new UserInfo();
        $userId = (new UserId())->setPhoneNumber($sms->phoneNumber);
        $userInfo->setUserIds([$userId]);

        if ('' !== $sms->accountId) {
            $userInfo->setAccountId($sms->accountId);
        }

        $event->setUserInfo($userInfo);

        return $event;
    }
}
