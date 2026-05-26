<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Service;

use GeekyBones\FraudDefenseBundle\Assessment\AssessmentClientInterface;
use GeekyBones\FraudDefenseBundle\Assessment\Exception\AssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentEventContext;
use GeekyBones\FraudDefenseBundle\Defense\Contract\SmsAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Event\SmsAssessmentEvent;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\SmsAssessmentMapper;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentResult;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use Symfony\Component\HttpFoundation\RequestStack;

final class SmsAssessment implements SmsAssessmentInterface
{
    public function __construct(
        private readonly SiteKeyResolver $keyResolver,
        private readonly AssessmentClientInterface $assessmentClient,
        private readonly SmsAssessmentEvent $eventBuilder,
        private readonly SmsAssessmentMapper $mapper,
        private readonly float $maxSmsRisk,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?string $expectedHostname = null,
    ) {
    }

    public function assess(
        string $token,
        string $action,
        SmsAssessmentContext $context,
        ?float $maxSmsRisk = null,
        string $type = 'score',
    ): SmsAssessmentResult {
        $threshold = $maxSmsRisk ?? $this->maxSmsRisk;
        $siteKey = $this->keyResolver->resolve($type);
        $request = $this->requestStack?->getCurrentRequest();

        $eventContext = new AssessmentEventContext($token, $siteKey, $action, $request);

        try {
            $event = $this->eventBuilder->build($eventContext, $context);
            $assessment = $this->assessmentClient->create($event);
        } catch (AssessmentException $e) {
            throw new DefenseAssessmentException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->mapper->map($assessment, $action, $threshold, $this->expectedHostname);
    }
}
