<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Client;

use Google\Cloud\RecaptchaEnterprise\V1\Client\RecaptchaEnterpriseServiceClient;

final class EnterpriseClientFactory
{
    private ?RecaptchaEnterpriseServiceClient $client = null;

    public function __construct(
        private readonly string $apiKey,
    ) {
    }

    public function get(): RecaptchaEnterpriseServiceClient
    {
        return $this->client ??= new RecaptchaEnterpriseServiceClient([
            'apiKey' => $this->apiKey,
            'transport' => 'rest',
        ]);
    }
}
