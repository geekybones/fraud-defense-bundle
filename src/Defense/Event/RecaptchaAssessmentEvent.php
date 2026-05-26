<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Event;

use GeekyBones\FraudDefenseBundle\Defense\AssessmentEventContext;
use GeekyBones\FraudDefenseBundle\Http\ClientIpResolver;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Symfony\Component\HttpFoundation\Request;

final class RecaptchaAssessmentEvent
{
    public function __construct(
        private readonly ClientIpResolver $clientIpResolver,
    ) {
    }

    public function build(AssessmentEventContext $context): Event
    {
        $event = (new Event())
            ->setToken($context->token)
            ->setSiteKey($context->siteKey);

        if ('' !== $context->action) {
            $event->setExpectedAction($context->action);
        }

        if ($context->request instanceof Request) {
            $event->setUserIpAddress($this->clientIpResolver->resolve($context->request));
            $event->setUserAgent($context->request->headers->get('User-Agent') ?? '');
        }

        return $event;
    }
}
