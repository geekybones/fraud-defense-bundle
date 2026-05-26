<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Form;

use GeekyBones\FraudDefenseBundle\Defense\Contract\TransactionAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Checkout: one assessment with reCAPTCHA token and transaction data. */
class TransactionRecaptchaType extends AbstractRecaptchaFormType
{
    public function __construct(
        SiteKeyResolver $keyResolver,
        private readonly ?TransactionAssessmentInterface $transactionAssessment = null,
        bool $failOnApiError = true,
        private readonly float $defaultMinScore = 0.5,
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

                $this->assessTransaction($token, $form, $options);
            },
        );
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    private function assessTransaction(string $token, FormInterface $form, array $options): void
    {
        if (!$this->transactionAssessment instanceof TransactionAssessmentInterface) {
            throw new LogicException('TransactionRecaptchaType requires fraud_defense.transaction_defense.enabled: true.');
        }

        $parent = $form->getParent();
        if (!$parent instanceof FormInterface) {
            throw new LogicException('TransactionRecaptchaType must be added to a parent form.');
        }

        /** @var callable(FormInterface<mixed>): TransactionAssessmentContext $builder */
        $builder = $options['transaction_builder'];
        $transactionContext = $builder($parent);

        $minScore = $options['check_score']
            ? ($options['min_score'] ?? $this->defaultMinScore)
            : null;

        try {
            $result = $this->transactionAssessment->assess(
                token: $token,
                action: $options['action'],
                context: $transactionContext,
                maxTransactionRisk: $options['max_transaction_risk'],
                minScore: $minScore,
                type: $options['type'],
            );
        } catch (DefenseAssessmentException $e) {
            if (!$options['fail_on_api_error']) {
                $this->logger?->warning('Transaction Defense assessment failed; allowing form submission.', [
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
            'transaction_builder' => null,
            'max_transaction_risk' => null,
            'check_score' => false,
            'min_score' => null,
        ]);

        $resolver->setAllowedTypes('failed_message', ['null', 'string']);
        $resolver->setAllowedTypes('transaction_builder', 'callable');
        $resolver->setAllowedTypes('max_transaction_risk', ['null', 'float']);
        $resolver->setAllowedTypes('check_score', 'bool');
        $resolver->setAllowedTypes('min_score', ['null', 'float']);
        $resolver->setRequired('transaction_builder');
        $resolver->setInfo(
            'transaction_builder',
            'Callable (FormInterface $form): TransactionAssessmentContext for a single Transaction Defense assessment.',
        );
        $resolver->setInfo('check_score', 'When true, also enforce min_score on the same assessment.');
        $resolver->setInfo('max_transaction_risk', 'Per-field override of fraud_defense.transaction_defense.max_transaction_risk.');

        $resolver->setNormalizer('failed_message', static function (Options $options, ?string $value): string {
            if (null !== $value) {
                return $value;
            }

            return 'Your payment could not be verified. Please try again.';
        });
    }
}
