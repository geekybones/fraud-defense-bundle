<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Form;

use GeekyBones\FraudDefenseBundle\Defense\Contract\SmsAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentResult;
use GeekyBones\FraudDefenseBundle\Defense\SiteKeyResolver;
use GeekyBones\FraudDefenseBundle\Form\SmsRecaptchaType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Test\TypeTestCase;

#[AllowMockObjectsWithoutExpectations]
class SmsRecaptchaTypeTest extends TypeTestCase
{
    private MockObject&SmsAssessmentInterface $smsAssessment;

    protected function setUp(): void
    {
        $this->smsAssessment = $this->createMock(SmsAssessmentInterface::class);
        parent::setUp();
    }

    /**
     * @return array<int, FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        return [
            new SmsRecaptchaType(
                new SiteKeyResolver('test-site-key', 'checkbox-key'),
                $this->smsAssessment,
                failOnApiError: true,
            ),
        ];
    }

    /**
     * @return FormInterface<mixed>
     */
    private function buildForm(): FormInterface
    {
        $builder = $this->factory->createBuilder(FormType::class);
        $builder->add('phone');
        $builder->add('captcha', SmsRecaptchaType::class, [
            'action' => 'sms_otp',
            'sms_builder' => static fn (FormInterface $form): SmsAssessmentContext => new SmsAssessmentContext(
                phoneNumber: (string) $form->get('phone')->getData(),
                accountId: 'user-1',
            ),
        ]);

        return $builder->getForm();
    }

    public function test_uses_sms_defense_assessment(): void
    {
        $this->smsAssessment
            ->expects(self::once())
            ->method('assess')
            ->with('token', 'sms_otp', self::isInstanceOf(SmsAssessmentContext::class), null, 'score')
            ->willReturn(new SmsAssessmentResult(
                smsRisk: 0.1,
                reasons: [],
                tokenValid: true,
                action: 'sms_otp',
                passed: true,
            ));

        $form = $this->buildForm();
        $form->submit(['phone' => '+18005550175', 'captcha' => 'token']);

        self::assertTrue($form->isValid());
    }

    public function test_adds_error_when_assessment_fails(): void
    {
        $this->smsAssessment
            ->method('assess')
            ->willReturn(new SmsAssessmentResult(
                smsRisk: 0.9,
                reasons: [],
                tokenValid: true,
                action: 'sms_otp',
                passed: false,
            ));

        $form = $this->buildForm();
        $form->submit(['phone' => '+18005550175', 'captcha' => 'token']);

        self::assertFalse($form->isValid());
    }

    public function test_api_error_shows_exception_message(): void
    {
        $this->smsAssessment
            ->method('assess')
            ->willThrowException(new DefenseAssessmentException('SMS API down'));

        $form = $this->buildForm();
        $form->submit(['phone' => '+18005550175', 'captcha' => 'token']);

        self::assertFalse($form->isValid());
        self::assertStringContainsString('SMS API down', (string) $form->getErrors());
    }
}
