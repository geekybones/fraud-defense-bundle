<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Form;

use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;

/**
 * Shared widget rendering and form error helpers for reCAPTCHA form fields.
 *
 * @extends AbstractType<string|null>
 */
abstract class AbstractRecaptchaFormType extends AbstractType
{
    public function __construct(
        protected readonly SiteKeyResolver $keyResolver,
        protected readonly bool $failOnApiError = true,
        protected readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['site_key'] = $this->keyResolver->resolve($options['type']);
        $view->vars['action'] = $options['action'];
        $view->vars['recaptcha_type'] = $options['type'];
        $view->vars['load_script'] = $options['load_script'];
    }

    public function getBlockPrefix(): string
    {
        return 'recaptcha';
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    /**
     * @param FormInterface<mixed> $field
     */
    protected function addFormError(FormInterface $field, string $message): void
    {
        ($field->getParent() ?? $field)->addError(new FormError($message));
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function apiErrorMessage(Throwable $e, array $options): string
    {
        $message = trim($e->getMessage());

        return '' !== $message ? $message : $options['api_error_message'];
    }

    protected function configureBaseOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'action' => 'default',
            'type' => 'score',
            'missing_message' => null,
            'api_error_message' => 'Security verification is temporarily unavailable. Please try again later.',
            'fail_on_api_error' => $this->failOnApiError,
            'load_script' => true,
            'mapped' => false,
        ]);

        $resolver->setAllowedTypes('action', 'string');
        $resolver->setAllowedTypes('type', 'string');
        $resolver->setAllowedValues('type', ['score', 'checkbox']);
        $resolver->setAllowedTypes('missing_message', ['null', 'string']);
        $resolver->setAllowedTypes('api_error_message', 'string');
        $resolver->setAllowedTypes('fail_on_api_error', 'bool');
        $resolver->setAllowedTypes('load_script', 'bool');
        $resolver->setInfo('load_script', 'When false, the widget does not inject enterprise.js; load it with recaptcha_script() in your layout.');
        $resolver->setInfo('api_error_message', 'Fallback when an API exception has no message; otherwise the exception message is shown.');

        $resolver->setNormalizer('missing_message', static function (Options $options, ?string $value): string {
            if (null !== $value) {
                return $value;
            }

            return 'checkbox' === $options['type']
                ? 'Please tick "I\'m not a robot" to continue.'
                : 'Security token is missing. Please reload the page and try again.';
        });
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    protected function resolveTokenOrAddError(FormInterface $form, array $options): ?string
    {
        $token = $form->getData();

        if (empty($token)) {
            $this->addFormError($form, $options['missing_message']);

            return null;
        }

        return (string) $token;
    }
}
