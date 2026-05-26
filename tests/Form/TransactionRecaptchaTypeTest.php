<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Form;

use GeekyBones\FraudDefenseBundle\Defense\Contract\TransactionAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentResult;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionUser;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use GeekyBones\FraudDefenseBundle\Form\TransactionRecaptchaType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Test\TypeTestCase;

#[AllowMockObjectsWithoutExpectations]
class TransactionRecaptchaTypeTest extends TypeTestCase
{
    private MockObject&TransactionAssessmentInterface $transactionAssessment;

    protected function setUp(): void
    {
        $this->transactionAssessment = $this->createMock(TransactionAssessmentInterface::class);
        parent::setUp();
    }

    /**
     * @return array<int, FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        return [
            new TransactionRecaptchaType(
                new SiteKeyResolver('test-site-key', 'checkbox-key'),
                $this->transactionAssessment,
                failOnApiError: true,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $captchaOptions
     *
     * @return FormInterface<mixed>
     */
    private function buildForm(array $captchaOptions = []): FormInterface
    {
        $builder = $this->factory->createBuilder(FormType::class);
        $builder->add('amount');
        $builder->add('captcha', TransactionRecaptchaType::class, [
            'action' => 'purchase',
            'transaction_builder' => static fn (FormInterface $form): TransactionAssessmentContext => new TransactionAssessmentContext(
                paymentMethod: 'credit-card',
                cardBin: '411111',
                cardLastFour: '1234',
                currencyCode: 'USD',
                value: (float) $form->get('amount')->getData(),
                user: new TransactionUser(email: 'buyer@example.com'),
            ),
            ...$captchaOptions,
        ]);

        return $builder->getForm();
    }

    public function test_uses_transaction_defense_assessment(): void
    {
        $this->transactionAssessment
            ->expects(self::once())
            ->method('assess')
            ->with('token', 'purchase', self::isInstanceOf(TransactionAssessmentContext::class), null, null, 'score')
            ->willReturn(new TransactionAssessmentResult(
                transactionRisk: 0.1,
                riskReasons: [],
                tokenValid: true,
                action: 'purchase',
                passed: true,
            ));

        $form = $this->buildForm();
        $form->submit(['amount' => '99', 'captcha' => 'token']);

        self::assertTrue($form->isValid());
    }

    public function test_adds_error_when_assessment_fails(): void
    {
        $this->transactionAssessment
            ->method('assess')
            ->willReturn(new TransactionAssessmentResult(
                transactionRisk: 0.9,
                riskReasons: [],
                tokenValid: true,
                action: 'purchase',
                passed: false,
            ));

        $form = $this->buildForm();
        $form->submit(['amount' => '99', 'captcha' => 'token']);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('payment', (string) $form->getErrors());
    }

    public function test_forwards_check_score_to_assess(): void
    {
        $this->transactionAssessment
            ->expects(self::once())
            ->method('assess')
            ->with('token', 'purchase', self::isInstanceOf(TransactionAssessmentContext::class), null, 0.8, 'score')
            ->willReturn(new TransactionAssessmentResult(
                transactionRisk: 0.1,
                riskReasons: [],
                tokenValid: true,
                action: 'purchase',
                passed: true,
                score: 0.9,
            ));

        $form = $this->buildForm([
            'check_score' => true,
            'min_score' => 0.8,
        ]);
        $form->submit(['amount' => '99', 'captcha' => 'token']);

        self::assertTrue($form->isValid());
    }

    public function test_api_error_shows_exception_message(): void
    {
        $this->transactionAssessment
            ->method('assess')
            ->willThrowException(new DefenseAssessmentException('API down'));

        $form = $this->buildForm();
        $form->submit(['amount' => '99', 'captcha' => 'token']);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('API down', (string) $form->getErrors());
    }
}
