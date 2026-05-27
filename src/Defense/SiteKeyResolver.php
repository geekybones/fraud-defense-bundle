<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense;

use LogicException;

final class SiteKeyResolver
{
    public function __construct(
        private readonly ?string $siteKey,
        private readonly ?string $checkboxSiteKey,
    ) {
    }

    public function resolve(string $type): string
    {
        return match ($type) {
            'checkbox' => $this->checkboxSiteKey ?? throw new LogicException('No "checkbox_site_key" is configured under fraud_defense. It is required when using type: checkbox.'),
            default => $this->siteKey ?? throw new LogicException('No "site_key" is configured under fraud_defense. It is required when using the default recaptcha type.'),
        };
    }
}
