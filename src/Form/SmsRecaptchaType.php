<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Form;

use GeekyBones\FraudDefenseBundle\Defense\Contract\SmsAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** SMS OTP: one assessment with reCAPTCHA token and phone/account context. */
class SmsRecaptchaType extends AbstractRecaptchaFormType
{
    public function __construct(
        SiteKeyResolver $keyResolver,
        private readonly ?SmsAssessmentInterface $smsAssessment = null,
        bool $failOnApiError = true,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($keyResolver, $failOnApiError, $logger);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($options): void {
                $form = $event->getForm();
                $token = $this->resolveTokenOrAddError($form, $options);

                if (null === $token) {
                    return;
                }

                $this->assessSms($token, $form, $options);
            },
        );
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    private function assessSms(string $token, FormInterface $form, array $options): void
    {
        if (!$this->smsAssessment instanceof SmsAssessmentInterface) {
            throw new LogicException('SmsRecaptchaType requires fraud_defense.sms_defense.enabled: true.');
        }

        $parent = $form->getParent();
        if (!$parent instanceof FormInterface) {
            throw new LogicException('SmsRecaptchaType must be added to a parent form.');
        }

        /** @var callable(FormInterface<mixed>): SmsAssessmentContext $builder */
        $builder = $options['sms_builder'];
        $sms = $builder($parent);

        try {
            $result = $this->smsAssessment->assess(
                token: $token,
                action: $options['action'],
                context: $sms,
                maxSmsRisk: $options['max_sms_risk'],
                type: $options['type'],
            );
        } catch (DefenseAssessmentException $e) {
            if (!$options['fail_on_api_error']) {
                $this->logger?->warning('SMS Defense assessment failed; allowing form submission.', [
                    'exception' => $e,
                ]);

                return;
            }

            $this->addFormError($form, $this->apiErrorMessage($e, $options));

            return;
        }

        if (!$result->hasPassed()) {
            $this->addFormError($form, $options['failed_message']);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $this->configureBaseOptions($resolver);

        $resolver->setDefaults([
            'type' => 'score',
            'failed_message' => null,
            'sms_builder' => null,
            'max_sms_risk' => null,
        ]);

        $resolver->setAllowedTypes('failed_message', ['null', 'string']);
        $resolver->setAllowedTypes('sms_builder', 'callable');
        $resolver->setAllowedTypes('max_sms_risk', ['null', 'float']);
        $resolver->setRequired('sms_builder');
        $resolver->setInfo(
            'sms_builder',
            'Callable (FormInterface $form): SmsAssessmentContext with phoneNumber (E.164) and optional accountId.',
        );
        $resolver->setInfo('max_sms_risk', 'Per-field override of fraud_defense.sms_defense.max_sms_risk.');

        $resolver->setNormalizer('failed_message', static function (Options $options, ?string $value): string {
            if (null !== $value) {
                return $value;
            }

            return 'This phone number could not be verified. Please try again.';
        });
    }
}
