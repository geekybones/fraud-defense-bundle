<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Service;

use GeekyBones\FraudDefenseBundle\Assessment\AssessmentClientInterface;
use GeekyBones\FraudDefenseBundle\Assessment\Exception\AssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\AssessmentEventContext;
use GeekyBones\FraudDefenseBundle\Defense\Contract\TransactionAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Event\TransactionAssessmentEvent;
use GeekyBones\FraudDefenseBundle\Defense\Mapper\TransactionAssessmentMapper;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentResult;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use GeekyBones\FraudDefenseBundle\Defense\TransactionAssessmentContextValidator;
use Symfony\Component\HttpFoundation\RequestStack;

final class TransactionAssessment implements TransactionAssessmentInterface
{
    public function __construct(
        private readonly SiteKeyResolver $keyResolver,
        private readonly AssessmentClientInterface $assessmentClient,
        private readonly TransactionAssessmentEvent $eventBuilder,
        private readonly TransactionAssessmentMapper $mapper,
        private readonly float $maxTransactionRisk,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?string $expectedHostname = null,
    ) {
    }

    public function assess(
        string $token,
        string $action,
        TransactionAssessmentContext $context,
        ?float $maxTransactionRisk = null,
        ?float $minScore = null,
        string $type = 'score',
    ): TransactionAssessmentResult {
        $threshold = $maxTransactionRisk ?? $this->maxTransactionRisk;
        $siteKey = $this->keyResolver->resolve($type);
        $request = $this->requestStack?->getCurrentRequest();

        TransactionAssessmentContextValidator::assertMeetsMinimumRequirements($context);

        $eventContext = new AssessmentEventContext($token, $siteKey, $action, $request);

        try {
            $event = $this->eventBuilder->build($eventContext, $context);
            $assessment = $this->assessmentClient->create($event);
        } catch (AssessmentException $e) {
            throw new DefenseAssessmentException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->mapper->map(
            $assessment,
            $action,
            $threshold,
            $this->expectedHostname,
            minScore: $minScore,
        );
    }
}
