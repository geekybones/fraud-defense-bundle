<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Service;

use GeekyBones\FraudDefenseBundle\Assessment\AssessmentClientInterface;
use GeekyBones\FraudDefenseBundle\Assessment\Exception\AssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentEventContext;
use GeekyBones\FraudDefenseBundle\Defense\Contract\RecaptchaAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Event\RecaptchaAssessmentEvent;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\RecaptchaAssessmentMapper;
use GeekyBones\FraudDefenseBundle\Defense\Model\RecaptchaAssessmentResult;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use Symfony\Component\HttpFoundation\RequestStack;

final class RecaptchaAssessment implements RecaptchaAssessmentInterface
{
    public function __construct(
        private readonly SiteKeyResolver $keyResolver,
        private readonly AssessmentClientInterface $assessmentClient,
        private readonly RecaptchaAssessmentEvent $eventBuilder,
        private readonly RecaptchaAssessmentMapper $mapper,
        private readonly float $minScore,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?string $expectedHostname = null,
    ) {
    }

    public function assess(string $token, string $action = 'default', ?float $minScore = null, string $type = 'score'): RecaptchaAssessmentResult
    {
        $threshold = $minScore ?? $this->minScore;
        $siteKey = $this->keyResolver->resolve($type);
        $request = $this->requestStack?->getCurrentRequest();

        $context = new AssessmentEventContext($token, $siteKey, $action, $request);

        try {
            $event = $this->eventBuilder->build($context);
            $assessment = $this->assessmentClient->create($event);
        } catch (AssessmentException $e) {
            throw new DefenseAssessmentException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->mapper->map($assessment, $action, $threshold, $this->expectedHostname);
    }
}
