<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense;

use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use LogicException;
use PHPUnit\Framework\TestCase;

final class SiteKeyResolverTest extends TestCase
{
    public function test_resolves_score_site_key_by_default(): void
    {
        $resolver = new SiteKeyResolver('score-key', 'checkbox-key');

        self::assertSame('score-key', $resolver->resolve('score'));
    }

    public function test_resolves_checkbox_site_key(): void
    {
        $resolver = new SiteKeyResolver('score-key', 'checkbox-key');

        self::assertSame('checkbox-key', $resolver->resolve('checkbox'));
    }

    public function test_throws_when_checkbox_key_missing(): void
    {
        $resolver = new SiteKeyResolver('score-key', null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('checkbox_site_key');

        $resolver->resolve('checkbox');
    }
}
