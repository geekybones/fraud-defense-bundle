<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Assessment;

use GeekyBones\FraudDefenseBundle\Assessment\AssessmentTokenValidator;
use GeekyBones\FraudDefenseBundle\Assessment\Exception\AssessmentException;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;
use PHPUnit\Framework\TestCase;

final class AssessmentTokenValidatorTest extends TestCase
{
    public function test_skips_validation_when_no_token_was_sent(): void
    {
        $this->expectNotToPerformAssertions();

        AssessmentTokenValidator::assertValidWhenTokenSent(
            (new Event())->setToken(''),
            new Assessment(),
        );
    }

    public function test_accepts_valid_token(): void
    {
        $this->expectNotToPerformAssertions();

        AssessmentTokenValidator::assertValidWhenTokenSent(
            (new Event())->setToken('token'),
            (new Assessment())->setTokenProperties(
                (new TokenProperties())->setValid(true),
            ),
        );
    }

    public function test_rejects_expired_token(): void
    {
        $this->expectException(AssessmentException::class);
        $this->expectExceptionMessage('reCAPTCHA token is invalid (EXPIRED).');

        AssessmentTokenValidator::assertValidWhenTokenSent(
            (new Event())->setToken('expired'),
            (new Assessment())->setTokenProperties(
                (new TokenProperties())
                    ->setValid(false)
                    ->setInvalidReason(InvalidReason::EXPIRED),
            ),
        );
    }

    public function test_rejects_token_when_valid_is_false(): void
    {
        $this->expectException(AssessmentException::class);
        $this->expectExceptionMessage('reCAPTCHA token is invalid.');

        AssessmentTokenValidator::assertValidWhenTokenSent(
            (new Event())->setToken('token'),
            (new Assessment())->setTokenProperties(new TokenProperties()),
        );
    }

    public function test_rejects_missing_token_properties(): void
    {
        $this->expectException(AssessmentException::class);
        $this->expectExceptionMessage('no tokenProperties');

        AssessmentTokenValidator::assertValidWhenTokenSent(
            (new Event())->setToken('token'),
            new Assessment(),
        );
    }
}
