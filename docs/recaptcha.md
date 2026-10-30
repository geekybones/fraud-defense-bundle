# reCAPTCHA

Standard bot protection using the Google reCAPTCHA Enterprise score or checkbox widget.

Pick your path:

| You have | Go to |
|----------|-------|
| A Symfony form | [Symfony form](#symfony-form), the shortest route (3 steps) |
| A hand-written HTML form, AJAX call or API endpoint | [Programmatic usage](#programmatic-usage) |

Both use the same two widget types, described next.

---

## Score vs checkbox

| `type` | Widget | Site key used |
|--------|--------|---------------|
| `score` | Invisible; token submitted with the form | `fraud_defense.site_key` |
| `checkbox` | "I'm not a robot" checkbox | `fraud_defense.checkbox_site_key` (must be set) |

**Use the same `type` everywhere:** the form field or widget, the script (`recaptcha_script()`), and the server-side assessment. `score` and `checkbox` use different site keys, so mixing them makes the token invalid.

---

## Symfony form

### 1. Add the field

```php
use GeekyBones\FraudDefenseBundle\Form\RecaptchaType;

$builder->add('captcha', RecaptchaType::class, [
    'action' => 'login',
    'type'   => 'score',        // 'score' (invisible) or 'checkbox', see above
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

## Customising the form field

### Loading the script

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

### Client-side validation

This applies to the Symfony form field only. With [programmatic usage](#programmatic-usage) you control the page, so any browser-side check is yours to write.

The widget script guards the form's `submit` event:

- **checkbox:** submitting before the box is ticked is blocked and `missing_message` appears below the widget. Focus moves to the message, which has `role="alert"`. Ticking the box removes it, and an expired tick empties the token again.
- **score:** if no token has been obtained yet, the submit is blocked and the error is shown. The token is requested when the page loads.

It is off by default. Turn it on with `true`, or pass an array to customise it. Without a custom `message`, the text is `missing_message`:

```php
'client_validation' => true,   // default class and message

'client_validation' => [       // custom; both keys optional
    'class' => 'invalid-feedback d-block',          // e.g. Bootstrap
    'message' => 'Please confirm you are human.',   // defaults to missing_message
],
```

The error is a `<div role="alert">` with no built-in styling, so style it through the class (default `fraud-defense-recaptcha-error`).

#### Default messages

When `missing_message` is not set, it depends on `type`:

| `type` | Message |
|--------|---------|
| `checkbox` | Please tick "I'm not a robot" to continue. |
| `score` | Security token is missing. Please reload the page and try again. |

The server-side check is unchanged and remains the real validation. A form submitted from JavaScript with `form.submit()` does not fire a `submit` event and skips the client check; use `form.requestSubmit()` instead.

### Content Security Policy (nonce)

If your site sends a CSP header that requires a nonce on scripts, give the widget the same nonce. It is added to the widget's inline `<script>` and to the `enterprise.js` script it loads.

In Twig, pass it as a variable. This example uses `csp_nonce()` from [NelmioSecurityBundle](https://github.com/nelmio/NelmioSecurityBundle); use whatever returns your page's nonce:

```twig
{{ form_row(form.captcha, {nonce: csp_nonce('script')}) }}
```

If you already have the nonce as a string in PHP, use the option instead: `'nonce' => $nonce`.

With `load_script: false`, pass the nonce to the script helper (programmatic pages: see [CSP nonce with programmatic usage](#csp-nonce-with-programmatic-usage)):

```twig
{{ recaptcha_script('score', csp_nonce('script')) }}
```

The `nonce` is added only when it is a non-empty string. Google's `enterprise.js` may load more scripts itself, so check Google's reCAPTCHA Enterprise documentation on Content Security Policy for the extra rules your policy needs.

---

## Programmatic usage

Use this when you are not using the Symfony form type: a hand-written HTML form, an AJAX/fetch call, or an API endpoint. You do three things yourself: render the widget, send the token, and call `assess()`.

| Step | Where | What |
|------|-------|------|
| 1 | Controller | Render the page, and on submit validate the token with `assess()` |
| 2 | Twig | Load `enterprise.js` with `recaptcha_script()` and get a token with JavaScript, using `recaptcha_site_key()` |

### 1. Controller

```php
use GeekyBones\FraudDefenseBundle\Defense\Contract\RecaptchaAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewsletterController extends AbstractController
{
    #[Route('/newsletter', name: 'newsletter', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        RecaptchaAssessmentInterface $recaptcha,
    ): Response {
        if ($request->isMethod('POST')) {
            try {
                $result = $recaptcha->assess(
                    token:  (string) $request->request->get('recaptcha_token'),
                    action: 'newsletter',   // must match the action used in JavaScript
                );
            } catch (DefenseAssessmentException $e) {
                // Google API unreachable, or the token is expired, malformed or already used
                $this->addFlash('error', 'Verification is unavailable. Please try again.');

                return $this->redirectToRoute('newsletter');
            }

            if (!$result->hasPassed()) {
                $this->addFlash('error', 'Your request could not be verified.');

                return $this->redirectToRoute('newsletter');
            }

            // ... subscribe the user
        }

        return $this->render('newsletter.html.twig');
    }
}
```

`assess()` takes `$token`, `$action = 'default'`, `$minScore = null` (uses `fraud_defense.min_score`) and `$type = 'score'`. See [Result](#result) for what comes back.

### 2. Twig: score (invisible)

The token is requested when the user submits, so it is always fresh. Tokens expire after about 2 minutes and can be used only once.

```twig
{# templates/newsletter.html.twig #}
{% block javascripts %}
    {{ recaptcha_script('score') }}
{% endblock %}

<form method="post" id="newsletter-form">
    <input type="email" name="email" required>
    <input type="hidden" name="recaptcha_token" id="recaptcha_token">
    <button type="submit">Subscribe</button>
</form>

<script>
    const form = document.getElementById('newsletter-form');

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        grecaptcha.enterprise.ready(() => {
            grecaptcha.enterprise
                .execute({{ recaptcha_site_key('score')|json_encode|raw }}, { action: 'newsletter' })
                .then((token) => {
                    document.getElementById('recaptcha_token').value = token;
                    form.submit();   // form.submit() does not fire another submit event
                });
        });
    });
</script>
```

Two Twig functions are available:

| Function | Returns |
|----------|---------|
| `recaptcha_script(type, nonce)` | The `<script>` tag for `enterprise.js`. Put it once in your layout, not once per form. `nonce` is optional, see [CSP nonce](#csp-nonce-with-programmatic-usage) |
| `recaptcha_site_key(type)` | The public site key for `type` (`'score'` by default, or `'checkbox'`). Safe to render in HTML |

### CSP nonce with programmatic usage

With a nonce-based CSP, two scripts on your page need the nonce. The bundle can add it to the first only:

```twig
{# 1. enterprise.js: the bundle adds the nonce #}
{{ recaptcha_script('score', csp_nonce('script')) }}

{# 2. your own inline script that calls grecaptcha: add the nonce yourself #}
<script nonce="{{ csp_nonce('script') }}">
    grecaptcha.enterprise.ready(() => { /* ... */ });
</script>
```

Without the nonce on your own inline script, the browser blocks it and no token is requested. `csp_nonce()` is from [NelmioSecurityBundle](https://github.com/nelmio/NelmioSecurityBundle); use whatever returns your page's nonce. For the Symfony form field, see [Content Security Policy (nonce)](#content-security-policy-nonce).

### Variants

#### Checkbox widget

Use `type: 'checkbox'` for the script, the site key and `assess()` (see [Score vs checkbox](#score-vs-checkbox)). `fraud_defense.checkbox_site_key` must be set.

```php
$recaptcha->assess(token: $token, action: 'newsletter', type: 'checkbox');
```

```twig
{{ recaptcha_script('checkbox') }}

<form method="post" id="newsletter-form">
    <input type="email" name="email" required>
    <input type="hidden" name="recaptcha_token" id="recaptcha_token">
    <div id="recaptcha-box"></div>
    <button type="submit">Subscribe</button>
</form>

<script>
    // enterprise.js is loaded async, so wait for it before rendering.
    window.addEventListener('load', () => {
        grecaptcha.enterprise.ready(() => {
            grecaptcha.enterprise.render('recaptcha-box', {
                sitekey: {{ recaptcha_site_key('checkbox')|json_encode|raw }},
                action: 'newsletter',
                callback: (token) => { document.getElementById('recaptcha_token').value = token; },
                'expired-callback': () => { document.getElementById('recaptcha_token').value = ''; },
            });
        });
    });
</script>
```

The checkbox widget gives a token only after the user ticks it. `assess()` does not check for an empty token itself, so test it first (`'' === $token`) and show your own message.

#### JSON / fetch (no `<form>`)

```javascript
async function subscribe(email) {
    const token = await new Promise((resolve) =>
        grecaptcha.enterprise.ready(() =>
            grecaptcha.enterprise.execute(SITE_KEY, { action: 'newsletter' }).then(resolve)
        )
    );

    return fetch('/api/newsletter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, recaptcha_token: token }),
    });
}
```

```php
$data = $request->toArray();
$result = $recaptcha->assess((string) ($data['recaptcha_token'] ?? ''), 'newsletter');
```

Get `SITE_KEY` from Twig with `{{ recaptcha_site_key('score')|json_encode|raw }}`, or put it in a `data-` attribute.

### Rules to remember

- **The action must match.** The action in `execute()` / `render()` and in `assess()` must be the same string. Pass `''` to `assess()` to skip the check.
- **One token, one check.** A token is single-use. Do not reuse it for a retry; request a new one.
- **Server decides.** A browser-side check is only a convenience. Always call `assess()` on the server.

---

## Reference

### Form field options

| Option | Default | Description |
|--------|---------|-------------|
| `action` | `'default'` | Action name sent with the token; pass `''` to skip action matching |
| `type` | `'score'` | `'score'` or `'checkbox'` |
| `min_score` | `null` | Override `fraud_defense.min_score`; `null` uses the configured default |
| `load_script` | `true` | Set `false` to load `enterprise.js` from your layout via `recaptcha_script()` |
| `fail_on_api_error` | from config | `false` allows the form through when the Google API is unreachable |
| `nonce` | `null` | CSP nonce for the widget's inline `<script>` and the `enterprise.js` it loads. See [Content Security Policy](#content-security-policy-nonce) |
| `missing_message` | by `type` | Error shown when the token is absent, on the server and in the browser when `client_validation` is on. See [Default messages](#default-messages) |
| `client_validation` | `false` | Browser-side check, see [Client-side validation](#client-side-validation). `true` enables it with defaults; an array may set `class` (default `fraud-defense-recaptcha-error`) and `message` (default `missing_message`) |
| `failed_message` | built-in | Error shown when the assessment does not pass |
| `api_error_message` | built-in | Error shown to the user when the Google API call fails. The real exception is logged, not shown |

### Result

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

### Error handling

`DefenseAssessmentException` is thrown for:

- Google API errors (network issues, invalid API key, quota exceeded)
- Expired, malformed, or already-used tokens
- Incomplete API responses

The exception message describes the cause. When using the form type, the user sees `api_error_message` as a form error and the real exception is logged (so Google's details, such as a blocked referer, are not exposed). When using programmatic assessment, catch it yourself.

Set `fail_on_api_error: false` (global or per field) to allow form submission when the API is unreachable. The exception is logged as a warning and the form passes through.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| `No "checkbox_site_key" is configured` | `type: 'checkbox'` without a checkbox key | Set `fraud_defense.checkbox_site_key` |
| `reCAPTCHA token is invalid` (expired, duplicate) | Token older than ~2 minutes or used twice | Request a new token for every submit |
| Token invalid right after setup | Widget and server use different `type`s or site keys | Use the same type everywhere, see [Score vs checkbox](#score-vs-checkbox) |
| Request fails with "could not be verified" | Action differs between JS and `assess()`, or `expected_hostname` does not match the page | Make the action strings identical; set `expected_hostname: null` while developing locally |
| Token field stays empty | `enterprise.js` never loaded (for example `load_script: false` without `recaptcha_script()`) | Add `{{ recaptcha_script('score') }}` to your layout and check the browser console for `[FraudDefense]` messages |
| Submit is not blocked with `client_validation` on | The form was submitted with `form.submit()` | Use `form.requestSubmit()` |
| Google API error (403, quota, bad key) | Wrong `project_id` or `api_key`, or API not enabled | Check [configuration.md](configuration.md) and the GCP console |
