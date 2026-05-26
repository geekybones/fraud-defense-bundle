# Transaction Defense

Assess checkout and payment risk before capturing a transaction, using Google reCAPTCHA Enterprise [Fraud Prevention](https://cloud.google.com/recaptcha/docs/fraud-prevention).

---

## Before you start

### GCP Console setup

Enable `transaction_defense` in your config and complete **all three integration steps** in the Google Cloud Console. The API does not return `fraudPreventionAssessment` until every step is finished — enabling the toggle alone is not enough.

**Steps (in the console, under your reCAPTCHA key → Transaction defense):**

1. **Send transaction data in assessments** — the bundle handles this via `TransactionAssessmentContext`.
2. **Interpret assessments** — acknowledge you have read and will act on `transactionRisk`.
3. **Annotate transaction events** — confirm you will send outcome annotations to Google.

Use the same GCP project and site key as configured in `fraud_defense.project_id` and `fraud_defense.site_key`. Google recommends a **score key** (not checkbox) for checkout flows.

### Bundle configuration

```yaml
fraud_defense:
    project_id: '%env(FRAUD_DEFENSE_PROJECT_ID)%'
    api_key:    '%env(FRAUD_DEFENSE_API_KEY)%'
    site_key:   '%env(FRAUD_DEFENSE_SITE_KEY)%'
    transaction_defense:
        enabled:              true
        max_transaction_risk: 0.7   # block when transactionRisk >= this (0.0–1.0)
```

---

## Minimum TransactionAssessmentContext

Google requires **`paymentMethod`** plus **at least one** of:

| Option | Required fields |
|--------|----------------|
| Card | `cardBin` **and** `cardLastFour` |
| User | `user.accountId`, `user.email`, or `user.phoneNumber` |

The bundle validates this before calling Google and throws `DefenseAssessmentException` if the context is incomplete. Additional fields are strongly recommended:

```php
new TransactionAssessmentContext(
    paymentMethod: 'credit-card',
    currencyCode:  'USD',
    value:         99.99,          // omitted from request when 0
    transactionId: 'order-123',
    cardBin:       '411111',
    cardLastFour:  '1234',
    user: new TransactionUser(
        accountId: 'user-42',
        email:     'buyer@example.com',
    ),
);
```

---

## Symfony form (recommended)

`TransactionRecaptchaType` sends the reCAPTCHA token and transaction data in a single assessment call. No separate `RecaptchaAssessment` call is needed.

### 1. Form type

```php
use GeekyBones\FraudDefenseBundle\Form\TransactionRecaptchaType;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

final class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('orderId', TextType::class)
            ->add('amount', MoneyType::class)
            ->add('captcha', TransactionRecaptchaType::class, [
                'action' => 'purchase',
                'transaction_builder' => static function (FormInterface $form): TransactionAssessmentContext {
                    return new TransactionAssessmentContext(
                        paymentMethod: 'credit-card',
                        currencyCode:  'USD',
                        value:         (float) $form->get('amount')->getData(),
                        transactionId: (string) $form->get('orderId')->getData(),
                        cardBin:       '411111',      // replace with real card data
                        cardLastFour:  '1234',
                        user: new TransactionUser(
                            accountId: 'user-42',
                            email:     'buyer@example.com',
                        ),
                    );
                },
            ]);
    }
}
```

The `transaction_builder` closure receives the **parent form**, so you can read other fields like `amount` and `orderId`. Map real card data from your checkout fields rather than using hard-coded values.

### 2. Template

```twig
{{ form_start(form) }}
    {{ form_errors(form) }}
    {{ form_row(form.orderId) }}
    {{ form_row(form.amount) }}
    {{ form_row(form.captcha) }}
    <button type="submit">Pay</button>
{{ form_end(form) }}
```

Use `{{ form_errors(form) }}` on the **parent form**. The captcha field is hidden; errors are attached to the root form, not to `form.captcha`.

### 3. Controller

```php
public function checkout(Request $request): Response
{
    $form = $this->createForm(CheckoutType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Transaction Defense passed — capture payment
    }

    return $this->render('checkout.html.twig', ['form' => $form]);
}
```

---

## Form field options

| Option | Default | Description |
|--------|---------|-------------|
| `action` | `'default'` | Action name sent with the token; pass `''` to skip action matching |
| `type` | `'score'` | `'score'` or `'checkbox'`; selects the site key for the widget and assessment |
| `transaction_builder` | — | **Required.** `fn (FormInterface $form): TransactionAssessmentContext` |
| `max_transaction_risk` | from config | Per-field override of `transaction_defense.max_transaction_risk` |
| `check_score` | `false` | Also enforce `min_score` against the reCAPTCHA score in the same response |
| `min_score` | from config | Minimum reCAPTCHA score required when `check_score` is `true` |
| `load_script` | `true` | Set `false` to load `enterprise.js` from your layout via `recaptcha_script()` |
| `fail_on_api_error` | from config | `false` allows the form through when the Google API is unreachable |
| `missing_message` | built-in | Error shown when the token is absent |
| `failed_message` | built-in | Error shown when the transaction risk check fails |
| `api_error_message` | built-in | Fallback error when a `DefenseAssessmentException` has an empty message |

---

## Programmatic usage

Inject `TransactionAssessmentInterface` when assessing transactions outside a form:

**Client-side**

```javascript
grecaptcha.enterprise.ready(() => {
    grecaptcha.enterprise.execute('YOUR_SITE_KEY', { action: 'purchase' })
        .then(token => { /* include token in the request body */ });
});
```

**Server-side**

```php
use GeekyBones\FraudDefenseBundle\Defense\Contract\TransactionAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionUser;

public function __construct(
    private readonly TransactionAssessmentInterface $transactionAssessment,
) {}

public function purchase(Request $request): Response
{
    $context = new TransactionAssessmentContext(
        paymentMethod: 'credit-card',
        currencyCode:  'USD',
        value:         99.99,
        transactionId: 'order-123',
        cardBin:       '411111',
        cardLastFour:  '1234',
        user: new TransactionUser(accountId: 'user-42', email: 'buyer@example.com'),
    );

    try {
        $result = $this->transactionAssessment->assess(
            token:               $request->request->get('recaptcha_token'),
            action:              'purchase',
            context:             $context,
            maxTransactionRisk:  0.7,   // optional per-call override
            minScore:            0.5,   // optional reCAPTCHA score floor
        );
    } catch (DefenseAssessmentException $e) {
        // API error, invalid token, context validation failed, or missing fraudPreventionAssessment
        throw $e;
    }

    if (!$result->hasPassed()) {
        // block payment — inspect getTransactionRisk() and getRiskReasons()
    }
}
```

---

## TransactionAssessmentContext reference

| Field | Type | Notes |
|-------|------|-------|
| `paymentMethod` | `string` | Required (e.g. `'credit-card'`, `'paypal'`) |
| `cardBin` | `string` | First 6 digits; required unless a user identifier is provided |
| `cardLastFour` | `string` | Last 4 digits; required with `cardBin` |
| `user` | `?TransactionUser` | See below; required unless card details are provided |
| `currencyCode` | `string` | ISO 4217 code (e.g. `'USD'`) — strongly recommended |
| `value` | `float` | Transaction amount; omitted from the request when `0` |
| `transactionId` | `string` | Your order or transaction ID — strongly recommended |
| `billingAddress` | `?TransactionAddress` | See below |
| `shippingAddress` | `?TransactionAddress` | See below |
| `shippingValue` | `float` | Shipping cost |

### TransactionUser

| Field | Type | Notes |
|-------|------|-------|
| `accountId` | `string` | Your internal user ID |
| `email` | `string` | Buyer email address |
| `phoneNumber` | `string` | E.164 format (e.g. `'+18005550175'`) |

At least one of `accountId`, `email`, or `phoneNumber` must be non-empty when card details are not provided.

### TransactionAddress

| Field | Type | Notes |
|-------|------|-------|
| `regionCode` | `string` | ISO 3166-1 alpha-2 country code — **required when set** |
| `postalCode` | `string` | Postal / ZIP code — **required when set** |
| `recipient` | `string` | Full recipient name |
| `addressLines` | `list<string>` | Street address lines |
| `locality` | `string` | City or town |
| `administrativeArea` | `string` | State, province, or region |

---

## Result

`TransactionAssessmentInterface::assess()` returns `TransactionAssessmentResult`:

| Method | Returns | Description |
|--------|---------|-------------|
| `hasPassed()` | `bool` | `true` when all checks pass |
| `getTransactionRisk()` | `float` | Risk score from Google (0.0 = low risk, 1.0 = high risk) |
| `getRiskReasons()` | `list<int\|string>` | Risk reason codes from Google |
| `getScore()` | `?float` | reCAPTCHA score when risk analysis is present (`null` otherwise) |
| `isTokenValid()` | `bool` | Whether Google accepted the token |
| `getAction()` | `string` | Action string from the token |
| `getInvalidReason()` | `int` | Invalid-token reason code (0 when valid) |
| `getRaw()` | `array` | Full Google assessment response as an array |

**Pass rules** — all must be true:

1. Token is valid
2. Action matches (skipped when action is `''`)
3. Hostname matches (skipped when not configured)
4. `transactionRisk < max_transaction_risk` (strict less-than — equal to threshold blocks)
5. When `check_score` is `true`: `score >= min_score` (non-strict — equal passes)

---

## Troubleshooting

### "did not return fraudPreventionAssessment"

The API response is missing `fraudPreventionAssessment`. Work through this checklist:

| Cause | Fix |
|-------|-----|
| Console setup incomplete | Finish **all three** integration steps in the Transaction defense tab — not just the enable toggle |
| Thin `TransactionAssessmentContext` | Provide `cardBin` + `cardLastFour` **or** at least one of `user.accountId`, `user.email`, `user.phoneNumber` |
| Wrong project or site key | The `project_id` and site key must match what is configured in the GCP console |
| `value` is `0` | Use a positive `value`; zero is omitted from the request |

### Other error messages

| Message | Cause / fix |
|---------|-------------|
| `TransactionAssessmentContext is incomplete for Transaction Defense.` | Context validation failed server-side — add the required fields listed above |
| `TransactionRecaptchaType requires fraud_defense.transaction_defense.enabled: true.` | Set `transaction_defense.enabled: true` in your YAML config |
| `reCAPTCHA token is invalid` / `EXPIRED` / `DUPE` | Token expired or reused; generate a fresh token per form submit |

Log `TransactionAssessmentResult::getRaw()` in development to inspect the full Google response.
