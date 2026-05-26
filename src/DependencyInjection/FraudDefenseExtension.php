<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\DependencyInjection;

use GeekyBones\FraudDefenseBundle\Defense\Contract\SmsAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\Contract\TransactionAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\Service\SmsAssessment;
use GeekyBones\FraudDefenseBundle\Defense\Service\TransactionAssessment;
use GeekyBones\FraudDefenseBundle\Form\SmsRecaptchaType;
use GeekyBones\FraudDefenseBundle\Form\TransactionRecaptchaType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class FraudDefenseExtension extends Extension implements PrependExtensionInterface
{
    private const FORM_THEME = '@FraudDefense/recaptcha.html.twig';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.yaml');

        $container->setParameter('fraud_defense.project_id', $config['project_id']);
        $container->setParameter('fraud_defense.api_key', $config['api_key']);
        $container->setParameter('fraud_defense.site_key', $config['site_key']);
        $container->setParameter('fraud_defense.checkbox_site_key', $config['checkbox_site_key']);
        $container->setParameter('fraud_defense.min_score', $config['min_score']);
        $container->setParameter('fraud_defense.expected_hostname', $config['expected_hostname']);
        $container->setParameter('fraud_defense.fail_on_api_error', $config['fail_on_api_error']);

        $transactionDefense = $config['transaction_defense'];
        if ($transactionDefense['enabled']) {
            $container->setParameter('fraud_defense.transaction_defense.max_transaction_risk', $transactionDefense['max_transaction_risk']);
            $this->wireTransactionRecaptchaType($container);
        } else {
            if ($container->hasDefinition(TransactionAssessment::class)) {
                $container->removeDefinition(TransactionAssessment::class);
            }
            if ($container->hasAlias(TransactionAssessmentInterface::class)) {
                $container->removeAlias(TransactionAssessmentInterface::class);
            }
        }

        $smsDefense = $config['sms_defense'];
        if ($smsDefense['enabled']) {
            $container->setParameter('fraud_defense.sms_defense.max_sms_risk', $smsDefense['max_sms_risk']);
            $this->wireSmsRecaptchaType($container);
        } else {
            if ($container->hasDefinition(SmsAssessment::class)) {
                $container->removeDefinition(SmsAssessment::class);
            }
            if ($container->hasAlias(SmsAssessmentInterface::class)) {
                $container->removeAlias(SmsAssessmentInterface::class);
            }
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('twig') || $this->hasFormTheme($container)) {
            return;
        }

        $container->prependExtensionConfig('twig', [
            'form_themes' => [self::FORM_THEME],
        ]);
    }

    private function wireTransactionRecaptchaType(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(TransactionRecaptchaType::class)) {
            return;
        }

        $container->getDefinition(TransactionRecaptchaType::class)
            ->setArgument('$transactionAssessment', new Reference(TransactionAssessmentInterface::class));
    }

    private function wireSmsRecaptchaType(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SmsRecaptchaType::class)) {
            return;
        }

        $container->getDefinition(SmsRecaptchaType::class)
            ->setArgument('$smsAssessment', new Reference(SmsAssessmentInterface::class));
    }

    private function hasFormTheme(ContainerBuilder $container): bool
    {
        foreach ($container->getExtensionConfig('twig') as $config) {
            foreach ($config['form_themes'] ?? [] as $theme) {
                if (self::FORM_THEME === $theme) {
                    return true;
                }
            }
        }

        return false;
    }
}
