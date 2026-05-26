<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Form;

use GeekyBones\FraudDefenseBundle\Defense\Contract\RecaptchaAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\RecaptchaAssessmentResult;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use GeekyBones\FraudDefenseBundle\Form\RecaptchaType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Test\TypeTestCase;

#[AllowMockObjectsWithoutExpectations]
class RecaptchaTypeTest extends TypeTestCase
{
    private MockObject&RecaptchaAssessmentInterface $assessment;

    protected function setUp(): void
    {
        $this->assessment = $this->createMock(RecaptchaAssessmentInterface::class);
        parent::setUp();
    }

    /**
     * @return array<int, FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        return [
            new RecaptchaType(new SiteKeyResolver('test-site-key', 'checkbox-key'), $this->assessment, failOnApiError: true),
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return FormInterface<mixed>
     */
    private function buildForm(array $options = []): FormInterface
    {
        $builder = $this->factory->createBuilder(FormType::class);
        $builder->add('captcha', RecaptchaType::class, ['action' => 'login', ...$options]);

        return $builder->getForm();
    }

    private function makeResult(bool $passed, float $score = 0.9): RecaptchaAssessmentResult
    {
        return new RecaptchaAssessmentResult(
            score: $score,
            reasons: [],
            tokenValid: $passed,
            action: 'login',
            passed: $passed,
        );
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return FormInterface<string|null>
     */
    private function captchaField(FormInterface $form): FormInterface
    {
        $captcha = $form->get('captcha');
        self::assertInstanceOf(FormInterface::class, $captcha);

        return $captcha;
    }

    public function test_form_is_invalid_when_token_is_empty(): void
    {
        $this->assessment->expects(self::never())->method('assess');

        $form = $this->buildForm();
        $form->submit(['captcha' => '']);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getErrors());
        self::assertCount(0, $this->captchaField($form)->getErrors());
    }

    public function test_form_is_invalid_when_token_is_missing(): void
    {
        $this->assessment->expects(self::never())->method('assess');

        $form = $this->buildForm();
        $form->submit([]);

        self::assertFalse($form->isValid());
    }

    public function test_form_is_invalid_when_validator_returns_failed(): void
    {
        $this->assessment
            ->expects(self::once())
            ->method('assess')
            ->with('some-token', 'login', null, 'score')
            ->willReturn($this->makeResult(false));

        $form = $this->buildForm();
        $form->submit(['captcha' => 'some-token']);

        self::assertFalse($form->isValid());
    }

    public function test_form_is_valid_when_validator_returns_pass(): void
    {
        $this->assessment
            ->expects(self::once())
            ->method('assess')
            ->with('valid-token', 'login', null, 'score')
            ->willReturn($this->makeResult(true));

        $form = $this->buildForm();
        $form->submit(['captcha' => 'valid-token']);

        self::assertTrue($form->isValid());
    }

    public function test_min_score_option_is_forwarded_to_validator(): void
    {
        $this->assessment
            ->expects(self::once())
            ->method('assess')
            ->with('valid-token', 'login', 0.7, 'score')
            ->willReturn($this->makeResult(true));

        $form = $this->buildForm(['min_score' => 0.7]);
        $form->submit(['captcha' => 'valid-token']);

        self::assertTrue($form->isValid());
    }

    public function test_type_option_is_forwarded_to_validator(): void
    {
        $this->assessment
            ->expects(self::once())
            ->method('assess')
            ->with('valid-token', 'login', null, 'checkbox')
            ->willReturn($this->makeResult(true));

        $form = $this->buildForm(['type' => 'checkbox']);
        $form->submit(['captcha' => 'valid-token']);

        self::assertTrue($form->isValid());
    }

    public function test_default_action_is_default(): void
    {
        $this->assessment
            ->expects(self::once())
            ->method('assess')
            ->with('valid-token', 'default', null, 'score')
            ->willReturn($this->makeResult(true));

        $builder = $this->factory->createBuilder(FormType::class);
        $builder->add('captcha', RecaptchaType::class);
        $form = $builder->getForm();
        $form->submit(['captcha' => 'valid-token']);

        self::assertTrue($form->isValid());
    }

    public function test_api_error_invalidates_form_when_fail_on_api_error_is_true(): void
    {
        $this->assessment
            ->expects(self::once())
            ->method('assess')
            ->willThrowException(new DefenseAssessmentException('API down'));

        $form = $this->buildForm(['fail_on_api_error' => true]);
        $form->submit(['captcha' => 'token']);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('API down', (string) $form->getErrors());
    }

    public function test_api_error_falls_back_to_api_error_message_when_exception_message_is_empty(): void
    {
        $this->assessment
            ->expects(self::once())
            ->method('assess')
            ->willThrowException(new DefenseAssessmentException(''));

        $form = $this->buildForm([
            'fail_on_api_error' => true,
            'api_error_message' => 'Custom fallback message.',
        ]);
        $form->submit(['captcha' => 'token']);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('Custom fallback message.', (string) $form->getErrors());
    }

    public function test_api_error_is_ignored_when_fail_on_api_error_is_false(): void
    {
        $this->assessment
            ->expects(self::once())
            ->method('assess')
            ->willThrowException(new DefenseAssessmentException('API down'));

        $form = $this->buildForm(['fail_on_api_error' => false]);
        $form->submit(['captcha' => 'token']);

        self::assertTrue($form->isValid());
    }

    public function test_checkbox_default_missing_message(): void
    {
        $this->assessment->expects(self::never())->method('assess');

        $form = $this->buildForm(['type' => 'checkbox']);
        $form->submit(['captcha' => '']);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('not a robot', (string) $form->getErrors());
    }

    public function test_checkbox_default_failed_message(): void
    {
        $this->assessment
            ->method('assess')
            ->willReturn($this->makeResult(false));

        $form = $this->buildForm(['type' => 'checkbox']);
        $form->submit(['captcha' => 'token']);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('not a robot', (string) $form->getErrors());
        self::assertStringNotContainsString('payment', (string) $form->getErrors());
    }

    public function test_score_default_failed_message_without_transaction_builder(): void
    {
        $this->assessment
            ->method('assess')
            ->willReturn($this->makeResult(false));

        $form = $this->buildForm(['type' => 'score']);
        $form->submit(['captcha' => 'token']);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('could not be verified', (string) $form->getErrors());
        self::assertStringNotContainsString('payment', (string) $form->getErrors());
    }

    public function test_load_script_defaults_to_true(): void
    {
        $form = $this->buildForm();
        $view = $this->captchaField($form)->createView();

        self::assertTrue($view->vars['load_script']);
    }

    public function test_load_script_can_be_disabled(): void
    {
        $form = $this->buildForm(['load_script' => false]);
        $view = $this->captchaField($form)->createView();

        self::assertFalse($view->vars['load_script']);
    }
}
