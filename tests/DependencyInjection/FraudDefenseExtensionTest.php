<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\DependencyInjection;

use GeekyBones\FraudDefenseBundle\Defense\Contract\RecaptchaAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\Contract\SmsAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\Contract\TransactionAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\Service\RecaptchaAssessment;
use GeekyBones\FraudDefenseBundle\Defense\Service\SmsAssessment;
use GeekyBones\FraudDefenseBundle\Defense\Service\TransactionAssessment;
use GeekyBones\FraudDefenseBundle\DependencyInjection\FraudDefenseExtension;
use GeekyBones\FraudDefenseBundle\Form\SmsRecaptchaType;
use GeekyBones\FraudDefenseBundle\Form\TransactionRecaptchaType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class FraudDefenseExtensionTest extends TestCase
{
    private const FORM_THEME = '@FraudDefense/recaptcha.html.twig';

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function baseConfig(array $extra = []): array
    {
        return array_merge([
            'project_id' => 'my-project',
            'api_key' => 'api-key',
            'site_key' => 'site-key',
            'checkbox_site_key' => null,
            'min_score' => 0.6,
            'expected_hostname' => 'www.example.com',
            'fail_on_api_error' => false,
            'transaction_defense' => [
                'enabled' => false,
            ],
            'sms_defense' => [
                'enabled' => false,
            ],
        ], $extra);
    }

    public function test_load_sets_root_parameters(): void
    {
        $container = new ContainerBuilder();
        (new FraudDefenseExtension())->load([$this->baseConfig()], $container);

        self::assertSame('my-project', $container->getParameter('fraud_defense.project_id'));
        self::assertSame('api-key', $container->getParameter('fraud_defense.api_key'));
        self::assertSame('site-key', $container->getParameter('fraud_defense.site_key'));
        self::assertSame(0.6, $container->getParameter('fraud_defense.min_score'));
        self::assertSame('www.example.com', $container->getParameter('fraud_defense.expected_hostname'));
        self::assertFalse($container->getParameter('fraud_defense.fail_on_api_error'));
    }

    public function test_load_always_registers_recaptcha(): void
    {
        $container = new ContainerBuilder();
        (new FraudDefenseExtension())->load([$this->baseConfig()], $container);

        self::assertTrue($container->hasDefinition(RecaptchaAssessment::class));
        self::assertTrue($container->hasAlias(RecaptchaAssessmentInterface::class));
        self::assertTrue($container->getAlias(RecaptchaAssessmentInterface::class)->isPublic());
    }

    public function test_load_registers_transaction_defense_when_enabled(): void
    {
        $container = new ContainerBuilder();
        (new FraudDefenseExtension())->load([$this->baseConfig([
            'transaction_defense' => ['enabled' => true, 'max_transaction_risk' => 0.8],
        ])], $container);

        self::assertSame(0.8, $container->getParameter('fraud_defense.transaction_defense.max_transaction_risk'));
        self::assertTrue($container->hasDefinition(TransactionAssessment::class));
        self::assertTrue($container->hasAlias(TransactionAssessmentInterface::class));

        $transactionRecaptchaType = $container->getDefinition(TransactionRecaptchaType::class);
        self::assertSame(
            TransactionAssessmentInterface::class,
            (string) $transactionRecaptchaType->getArgument('$transactionAssessment'),
        );
    }

    public function test_load_registers_sms_defense_when_enabled(): void
    {
        $container = new ContainerBuilder();
        (new FraudDefenseExtension())->load([$this->baseConfig([
            'sms_defense' => ['enabled' => true, 'max_sms_risk' => 0.4],
        ])], $container);

        self::assertSame(0.4, $container->getParameter('fraud_defense.sms_defense.max_sms_risk'));
        self::assertTrue($container->hasDefinition(SmsAssessment::class));
        self::assertTrue($container->hasAlias(SmsAssessmentInterface::class));

        $smsRecaptchaType = $container->getDefinition(SmsRecaptchaType::class);
        self::assertSame(
            SmsAssessmentInterface::class,
            (string) $smsRecaptchaType->getArgument('$smsAssessment'),
        );
    }

    public function test_prepend_does_nothing_without_twig_extension(): void
    {
        $container = new ContainerBuilder();
        (new FraudDefenseExtension())->prepend($container);

        self::assertFalse($container->hasExtension('twig'));
    }

    public function test_prepend_adds_form_theme_when_twig_extension_exists(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'twig';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        (new FraudDefenseExtension())->prepend($container);

        $configs = $container->getExtensionConfig('twig');
        self::assertContains(self::FORM_THEME, $configs[0]['form_themes'] ?? []);
    }

    public function test_prepend_skips_duplicate_form_theme(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'twig';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig('twig', [
            'form_themes' => [self::FORM_THEME],
        ]);

        (new FraudDefenseExtension())->prepend($container);

        $themes = [];
        foreach ($container->getExtensionConfig('twig') as $config) {
            $themes = array_merge($themes, $config['form_themes'] ?? []);
        }

        self::assertSame(1, \count(array_filter($themes, static fn (string $t): bool => self::FORM_THEME === $t)));
    }
}
