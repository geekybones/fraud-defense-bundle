<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Assessment;

use GeekyBones\FraudDefenseBundle\Assessment\Exception\AssessmentException;
use GeekyBones\FraudDefenseBundle\Client\EnterpriseClientFactory;
use Google\ApiCore\ApiException;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Client\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\CreateAssessmentRequest;
use Google\Cloud\RecaptchaEnterprise\V1\Event;

final class AssessmentClient implements AssessmentClientInterface
{
    public function __construct(
        private readonly string $projectId,
        private readonly EnterpriseClientFactory $clientFactory,
    ) {
    }

    public function create(Event $event): Assessment
    {
        try {
            $assessment = (new Assessment())->setEvent($event);

            $createRequest = (new CreateAssessmentRequest())
                ->setParent(RecaptchaEnterpriseServiceClient::projectName($this->projectId))
                ->setAssessment($assessment);

            $assessment = $this->clientFactory->get()->createAssessment($createRequest);
            AssessmentTokenValidator::assertValidWhenTokenSent($event, $assessment);

            return $assessment;
        } catch (ApiException $e) {
            throw $this->wrapApiException($e);
        }
    }

    private function wrapApiException(ApiException $e): AssessmentException
    {
        $decoded = json_decode($e->getMessage(), true);
        $detail = \is_array($decoded) && isset($decoded['message'])
            ? $decoded['message']
            : $e->getMessage();

        return new AssessmentException(
            \sprintf('Fraud Defense assessment failed: %s', $detail),
            $e->getCode(),
            $e,
        );
    }
}
