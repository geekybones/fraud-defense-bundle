<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Form;

use GeekyBones\FraudDefenseBundle\Defense\Contract\RecaptchaAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Standard reCAPTCHA Enterprise validation (score or checkbox). */
class RecaptchaType extends AbstractRecaptchaFormType
{
    public function __construct(
        SiteKeyResolver $keyResolver,
        private readonly RecaptchaAssessmentInterface $assessment,
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

                $this->assessRecaptcha($token, $form, $options);
            },
        );
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    private function assessRecaptcha(string $token, FormInterface $form, array $options): void
    {
        try {
            $result = $this->assessment->assess(
                $token,
                $options['action'],
                $options['min_score'],
                $options['type'],
            );
        } catch (DefenseAssessmentException $e) {
            if (!$options['fail_on_api_error']) {
                $this->logger?->warning('reCAPTCHA Enterprise assessment failed; allowing form submission.', [
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
            'min_score' => null,
            'failed_message' => null,
        ]);

        $resolver->setAllowedTypes('min_score', ['null', 'float']);
        $resolver->setAllowedTypes('failed_message', ['null', 'string']);

        $resolver->setNormalizer('failed_message', static function (Options $options, ?string $value): string {
            if (null !== $value) {
                return $value;
            }

            return 'checkbox' === $options['type']
                ? 'Please tick "I\'m not a robot" to continue.'
                : 'Your request could not be verified. Please try again.';
        });
    }
}
