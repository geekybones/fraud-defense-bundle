<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense;

use Twig\Attribute\AsTwigFunction;

final class RecaptchaTwigExtension
{
    private const ENTERPRISE_SCRIPT = 'https://www.google.com/recaptcha/enterprise.js';

    public function __construct(
        private readonly SiteKeyResolver $keyResolver,
    ) {
    }

    /**
     * @param string $type Widget type: score (default) or checkbox
     */
    #[AsTwigFunction(name: 'recaptcha_script', isSafe: ['html'])]
    public function renderScript(string $type = 'score'): string
    {
        $src = 'checkbox' === $type
            ? self::ENTERPRISE_SCRIPT.'?render=explicit'
            : self::ENTERPRISE_SCRIPT.'?render='.rawurlencode($this->keyResolver->resolve($type));

        return \sprintf('<script src="%s" async defer></script>', htmlspecialchars($src, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
    }
}
