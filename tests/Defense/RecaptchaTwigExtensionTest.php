<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense;

use GeekyBones\FraudDefenseBundle\Defense\RecaptchaTwigExtension;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use PHPUnit\Framework\TestCase;

final class RecaptchaTwigExtensionTest extends TestCase
{
    public function test_site_key_defaults_to_score_and_supports_checkbox(): void
    {
        $extension = new RecaptchaTwigExtension(new SiteKeyResolver('score-key', 'checkbox-key'));

        self::assertSame('score-key', $extension->siteKey());
        self::assertSame('checkbox-key', $extension->siteKey('checkbox'));
    }
}
