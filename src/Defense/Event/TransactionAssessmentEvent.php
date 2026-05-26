<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Event;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentEventContext;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\GoogleTransactionDataMapper;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use Google\Cloud\RecaptchaEnterprise\V1\Event;

final class TransactionAssessmentEvent
{
    public function __construct(
        private readonly RecaptchaAssessmentEvent $recaptchaAssessmentEvent,
        private readonly GoogleTransactionDataMapper $transactionDataMapper,
    ) {
    }

    public function build(AssessmentEventContext $context, TransactionAssessmentContext $transactionContext): Event
    {
        $event = $this->recaptchaAssessmentEvent->build($context);
        $event->setTransactionData($this->transactionDataMapper->map($transactionContext));

        return $event;
    }
}
