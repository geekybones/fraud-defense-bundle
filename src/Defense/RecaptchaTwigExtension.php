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
     * @param string      $type  Widget type: score (default) or checkbox
     * @param string|null $nonce CSP nonce for the script tag
     */
    #[AsTwigFunction(name: 'recaptcha_script', isSafe: ['html'])]
    public function renderScript(string $type = 'score', ?string $nonce = null): string
    {
        $src = 'checkbox' === $type
            ? self::ENTERPRISE_SCRIPT.'?render=explicit'
            : self::ENTERPRISE_SCRIPT.'?render='.rawurlencode($this->keyResolver->resolve($type));

        $attrs = \sprintf(' src="%s"', htmlspecialchars($src, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
        if (null !== $nonce && '' !== $nonce) {
            $attrs .= \sprintf(' nonce="%s"', htmlspecialchars($nonce, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
        }

        return '<script'.$attrs.' async defer></script>';
    }

    /**
     * @param string $type Widget type: score (default) or checkbox
     */
    #[AsTwigFunction(name: 'recaptcha_site_key')]
    public function siteKey(string $type = 'score'): string
    {
        return $this->keyResolver->resolve($type);
    }
}
