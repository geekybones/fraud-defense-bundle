# SMS Defense

Assess SMS OTP requests for [toll fraud](https://cloud.google.com/recaptcha-enterprise/docs/sms-defense) before sending a verification code.

---

## Before you start

### GCP Console setup

In the Google Cloud Console, open your reCAPTCHA key and enable **SMS toll fraud protection**. Without this, the API will not return `phoneFraudAssessment` regardless of what you send.

### Bundle configuration

```yaml
fraud_defense:
    project_id: '%env(FRAUD_DEFENSE_PROJECT_ID)%'
    api_key:    '%env(FRAUD_DEFENSE_API_KEY)%'
    site_key:   '%env(FRAUD_DEFENSE_SITE_KEY)%'
    sms_defense:
        enabled:      true
        max_sms_risk: 0.5   # block when smsRisk >= this (0.0–1.0)
```

---

## Symfony form (recommended)

`SmsRecaptchaType` sends the reCAPTCHA token and phone context in a single assessment call.

### 1. Form type

```php
use GeekyBones\FraudDefenseBundle\Form\SmsRecaptchaType;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentContext;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

final class OtpRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phone', TelType::class)
            ->add('captcha', SmsRecaptchaType::class, [
                'action' => 'sms_otp',
                'sms_builder' => static function (FormInterface $form): SmsAssessmentContext {
                    return new SmsAssessmentContext(
                        phoneNumber: (string) $form->get('phone')->getData(),  // E.164 e.g. +18005550175
                        accountId:   'user-42',  // optional; your internal user ID
                    );
                },
            ]);
    }
}
```

The `sms_builder` closure receives the **parent form**, so you can read other fields such as `phone`.

### 2. Template

```twig
{{ form_start(form) }}
    {{ form_errors(form) }}
    {{ form_row(form.phone) }}
    {{ form_row(form.captcha) }}
    <button type="submit">Send code</button>
{{ form_end(form) }}
```

Use `{{ form_errors(form) }}` on the parent form. Errors are attached to the root form, not to `form.captcha`.

### 3. Controller

```php
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    // SMS Defense passed — send OTP
}
```

---

## Form field options

| Option | Default | Description |
|--------|---------|-------------|
| `action` | `'default'` | Action name sent with the token; pass `''` to skip action matching |
| `type` | `'score'` | `'score'` or `'checkbox'`; selects the site key for the widget and assessment |
| `sms_builder` | — | **Required.** `fn (FormInterface $form): SmsAssessmentContext` |
| `max_sms_risk` | from config | Per-field override of `sms_defense.max_sms_risk` |
| `load_script` | `true` | Set `false` to load `enterprise.js` from your layout via `recaptcha_script()` |
| `fail_on_api_error` | from config | `false` allows the form through when the Google API is unreachable |
| `missing_message` | built-in | Error shown when the token is absent (see [defaults](recaptcha.md#default-messages)) |
| `client_validation` | `false` | Block the submit in the browser when no token exists and show an error. `true` uses the defaults; an array sets `class` and/or `message`. See [recaptcha.md](recaptcha.md#client-side-validation) |
| `failed_message` | built-in | Error shown when the SMS risk check fails |
| `api_error_message` | built-in | Error shown to the user when the Google API call fails. The real exception is logged, not shown |

---

## Programmatic usage

Inject `SmsAssessmentInterface` when assessing outside a form:

**Client-side**

Load the script once in your layout, then request a token when the user submits. The full flow (form, fetch and checkbox variants) is in [recaptcha.md](recaptcha.md#programmatic-usage).

```twig
{{ recaptcha_script('score') }}

<form method="post" id="otp-form">
    <input type="hidden" name="recaptcha_token" id="recaptcha_token">
    {# ... your fields ... #}
    <button type="submit">Send</button>
</form>

<script>
    const form = document.getElementById('otp-form');

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        grecaptcha.enterprise.ready(() => {
            grecaptcha.enterprise
                .execute({{ recaptcha_site_key('score')|json_encode|raw }}, { action: 'sms_otp' })
                .then((token) => {
                    document.getElementById('recaptcha_token').value = token;
                    form.submit();
                });
        });
    });
</script>
```

The `action` must match the one passed to `assess()`.

**Server-side**

```php
use GeekyBones\FraudDefenseBundle\Defense\Contract\SmsAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\SmsAssessmentContext;

public function __construct(
    private readonly SmsAssessmentInterface $smsAssessment,
) {}

public function requestOtp(Request $request): Response
{
    $context = new SmsAssessmentContext(
        phoneNumber: '+18005550175',  // E.164 format
        accountId:   $this->getUser()->getId(),  // optional
    );

    try {
        $result = $this->smsAssessment->assess(
            token:      $request->request->get('recaptcha_token'),
            action:     'sms_otp',
            context:    $context,
            maxSmsRisk: 0.5,   // optional per-call override
        );
    } catch (DefenseAssessmentException $e) {
        // API error, invalid token, or missing phoneFraudAssessment
        throw $e;
    }

    if (!$result->hasPassed()) {
        // block OTP — inspect getSmsRisk() and getReasons()
    }

    // send SMS
}
```

---

## SmsAssessmentContext reference

| Field | Type | Notes |
|-------|------|-------|
| `phoneNumber` | `string` | **Required.** E.164 format (e.g. `'+18005550175'`) |
| `accountId` | `string` | Your internal user ID — optional but recommended |

---

## Result

`SmsAssessmentInterface::assess()` returns `SmsAssessmentResult`:

| Method | Returns | Description |
|--------|---------|-------------|
| `hasPassed()` | `bool` | `true` when all checks pass |
| `getSmsRisk()` | `float` | Toll-fraud risk from Google (0.0 = low risk, 1.0 = high risk) |
| `getReasons()` | `list<int\|string>` | Risk reason codes from Google |
| `isTokenValid()` | `bool` | Whether Google accepted the token |
| `getAction()` | `string` | Action string from the token |
| `getInvalidReason()` | `int` | Invalid-token reason code (0 when valid) |
| `getRaw()` | `array` | Full Google assessment response as an array |

**Pass rules** — all must be true:

1. Token is valid
2. Action matches (skipped when action is `''`)
3. Hostname matches (skipped when not configured)
4. `smsRisk < max_sms_risk` (strict less-than — equal to threshold blocks)

---

## Error handling

`DefenseAssessmentException` is thrown for:

- Google API errors
- Expired, malformed, or already-used tokens
- Missing `phoneFraudAssessment` (SMS toll fraud protection not enabled on the key)

When using `SmsRecaptchaType`, the user sees `api_error_message` as a form error and the real exception is logged. When using programmatic assessment, catch it yourself.

---

## Troubleshooting

| Message / symptom | Cause / fix |
|-------------------|-------------|
| `SMS Defense assessment did not return phoneFraudAssessment` | SMS toll fraud protection is not enabled on your reCAPTCHA key in the Google Cloud Console, or no phone number was sent. Enable it and pass `phoneNumber` (E.164) |
| `SmsRecaptchaType requires fraud_defense.sms_defense.enabled: true.` | Set `sms_defense.enabled: true` in your YAML config |
| `reCAPTCHA token is invalid` (expired, duplicate) | Token older than about 2 minutes or used twice; request a new token for every submit |
| Request blocked with a valid token | The SMS risk was at or above `max_sms_risk` (equal blocks). Inspect `getSmsRisk()` and `getReasons()` |
| Token field stays empty | `enterprise.js` never loaded; add `{{ recaptcha_script('score') }}` to your layout and check the browser console |

Log `SmsAssessmentResult::getRaw()` in development to inspect the full Google response.
