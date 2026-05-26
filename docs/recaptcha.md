# reCAPTCHA

Standard bot protection using the Google reCAPTCHA Enterprise score or checkbox widget.

---

## Symfony form

### 1. Add the field

```php
use GeekyBones\FraudDefenseBundle\Form\RecaptchaType;

$builder->add('captcha', RecaptchaType::class, [
    'action' => 'login',
    'type'   => 'score',        // 'score' (invisible) or 'checkbox'
]);
```

### 2. Render in Twig

```twig
{{ form_start(form) }}
    {{ form_errors(form) }}
    {{ form_row(form.email) }}
    {{ form_row(form.password) }}
    {{ form_row(form.captcha) }}
    <button type="submit">Sign in</button>
{{ form_end(form) }}
```

The widget is hidden. Errors from a failed or missing token appear on the parent form via `{{ form_errors(form) }}`.

### 3. Handle in the controller

```php
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    // reCAPTCHA already passed — proceed
}
```

No extra code needed. The `POST_SUBMIT` listener runs the assessment automatically.

---

## Score vs checkbox

| `type` | Widget | Site key used |
|--------|--------|---------------|
| `score` | Invisible; token submitted with the form | `fraud_defense.site_key` |
| `checkbox` | "I'm not a robot" checkbox | `fraud_defense.checkbox_site_key` (must be set) |

The `type` value is used to select the correct site key in the widget, the Twig script helper, and the backend assessment. All three must use the same value.

---

## Loading the script

By default the widget renders a `<script>` tag alongside the hidden input. To load the script once from your base layout instead:

```php
$builder->add('captcha', RecaptchaType::class, [
    'load_script' => false,
    'action' => 'login',
]);
```

```twig
{# base layout — match the type used on your form fields #}
{{ recaptcha_script('score') }}
```

Use `recaptcha_script('checkbox')` when the field uses `'type' => 'checkbox'`.

---

## Form field options

| Option | Default | Description |
|--------|---------|-------------|
| `action` | `'default'` | Action name sent with the token; pass `''` to skip action matching |
| `type` | `'score'` | `'score'` or `'checkbox'` |
| `min_score` | `null` | Override `fraud_defense.min_score`; `null` uses the configured default |
| `load_script` | `true` | Set `false` to load `enterprise.js` from your layout via `recaptcha_script()` |
| `fail_on_api_error` | from config | `false` allows the form through when the Google API is unreachable |
| `missing_message` | built-in | Error shown when the token is absent |
| `failed_message` | built-in | Error shown when the assessment does not pass |
| `api_error_message` | built-in | Fallback error when a `DefenseAssessmentException` has an empty message |

---

## Programmatic usage

Inject `RecaptchaAssessmentInterface` when validating a token outside a form (API endpoints, webhooks):

```php
use GeekyBones\FraudDefenseBundle\Defense\Contract\RecaptchaAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;

public function __construct(
    private readonly RecaptchaAssessmentInterface $recaptcha,
) {}

public function submit(Request $request): Response
{
    try {
        $result = $this->recaptcha->assess(
            token:    $request->request->get('recaptcha_token'),
            action:   'login',
            minScore: 0.7,      // optional per-call override
            type:     'score',  // optional; defaults to 'score'
        );
    } catch (DefenseAssessmentException $e) {
        // API unreachable, token expired/invalid, or incomplete response
        throw $e;
    }

    if (!$result->hasPassed()) {
        // score below threshold, action mismatch, or hostname mismatch
    }
}
```

Obtain the token client-side via:

```javascript
grecaptcha.enterprise.ready(() => {
    grecaptcha.enterprise.execute('YOUR_SITE_KEY', { action: 'login' })
        .then(token => { /* include token in form data or request body */ });
});
```

---

## Result

`RecaptchaAssessmentInterface::assess()` returns `RecaptchaAssessmentResult`:

| Method | Returns | Description |
|--------|---------|-------------|
| `hasPassed()` | `bool` | `true` when all checks pass |
| `getScore()` | `float` | Risk score from Google (0.0 = bot, 1.0 = human) |
| `getReasons()` | `list<int\|string>` | Risk reason codes from Google |
| `isTokenValid()` | `bool` | Whether Google accepted the token |
| `getAction()` | `string` | Action string from the token |
| `getInvalidReason()` | `int` | Invalid-token reason code (0 when valid) |
| `getRaw()` | `array` | Full Google assessment response as an array |

**Pass rules** — all must be true:

- Token is valid (checked by `AssessmentClient` before mapping)
- Action matches `$expectedAction` (skipped when action is `''`)
- Hostname matches `expected_hostname` (skipped when not configured)
- `score >= min_score` (equal to the threshold passes)

---

## Error handling

`DefenseAssessmentException` is thrown for:

- Google API errors (network issues, invalid API key, quota exceeded)
- Expired, malformed, or already-used tokens
- Incomplete API responses

The exception message describes the cause. When using the form type, the message is attached as a form error. When using programmatic assessment, catch it yourself.

Set `fail_on_api_error: false` (global or per field) to allow form submission when the API is unreachable. The exception is logged as a warning and the form passes through.
