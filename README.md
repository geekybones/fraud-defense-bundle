# Fraud Defense Bundle

Symfony integration for [Google reCAPTCHA Enterprise](https://cloud.google.com/recaptcha-enterprise/docs) with support for standard bot protection, Transaction Defense, and SMS toll-fraud Defense. By [GeekyBones](https://geekybones.com).

| | |
|---|---|
| Package | `geekybones/fraud-defense-bundle` |
| PHP | `^8.2` |
| Symfony | `^7.0` or `^8.0` |
| License | MIT |

---

## Installation

```bash
composer require geekybones/fraud-defense-bundle
```

If Symfony Flex did not register the bundle automatically, add it to `config/bundles.php`:

```php
GeekyBones\FraudDefenseBundle\FraudDefenseBundle::class => ['all' => true],
```

---

## Google Cloud setup

1. Enable the **reCAPTCHA Enterprise API** in your GCP project.
2. Create one or more site keys for your domain (score key for invisible checks, checkbox key for the "I'm not a robot" widget).
3. Create an **API key** restricted to the reCAPTCHA Enterprise API.

For Transaction Defense and SMS Defense, additional console steps are required — see their dedicated guides.

---

## Configuration

Add environment variables:

```dotenv
FRAUD_DEFENSE_PROJECT_ID=your-gcp-project-id
FRAUD_DEFENSE_API_KEY=your-api-key
FRAUD_DEFENSE_SITE_KEY=your-score-site-key
# FRAUD_DEFENSE_CHECKBOX_SITE_KEY=your-checkbox-site-key  # only if using checkbox widget
```

Create `config/packages/fraud_defense.yaml`:

```yaml
fraud_defense:
    project_id: '%env(FRAUD_DEFENSE_PROJECT_ID)%'
    api_key:    '%env(FRAUD_DEFENSE_API_KEY)%'
    site_key:   '%env(FRAUD_DEFENSE_SITE_KEY)%'
    min_score:  0.5
```

Standard reCAPTCHA (`RecaptchaType`) works with this minimal config. Enable `transaction_defense` or `sms_defense` only when you need those flows.

See [configuration.md](docs/configuration.md) for all options.

---

## Usage

### Standard form protection

```php
use GeekyBones\FraudDefenseBundle\Form\RecaptchaType;

$builder->add('captcha', RecaptchaType::class, [
    'action' => 'login',
]);
```

```twig
{{ form_row(form.captcha) }}
```

Validation runs automatically on form submit. No controller changes needed.

### Programmatic

Inject the interface and call `assess()` directly — useful in APIs and controllers without forms:

```php
use GeekyBones\FraudDefenseBundle\Defense\Contract\RecaptchaAssessmentInterface;
use GeekyBones\FraudDefenseBundle\Defense\DefenseAssessmentException;

public function __construct(
    private readonly RecaptchaAssessmentInterface $recaptcha,
) {}

try {
    $result = $this->recaptcha->assess(
        token: $request->request->get('recaptcha_token'),
        action: 'login',
    );
} catch (DefenseAssessmentException $e) {
    // API error, invalid/expired token, or incomplete response
}

if (!$result->hasPassed()) {
    // score too low, action mismatch, or hostname mismatch
}
```

---

## Form types

| Form type | When to use | Requires |
|-----------|-------------|---------|
| `RecaptchaType` | Login, registration, contact — bot detection | Default config |
| `TransactionRecaptchaType` | Checkout, payment — fraud risk scoring | `transaction_defense.enabled: true` |
| `SmsRecaptchaType` | SMS OTP — toll-fraud detection | `sms_defense.enabled: true` |

All three share the same Twig widget (`{{ form_row(form.captcha) }}`).

---

## Documentation

| Guide | Contents |
|-------|----------|
| [configuration.md](docs/configuration.md) | All YAML options, environment variables, per-case examples |
| [recaptcha.md](docs/recaptcha.md) | Standard reCAPTCHA: form setup, script loading, programmatic usage |
| [transaction-defense.md](docs/transaction-defense.md) | Transaction Defense: GCP setup, form, context reference, troubleshooting |
| [sms-defense.md](docs/sms-defense.md) | SMS Defense: GCP setup, form, programmatic usage |
| [architecture.md](docs/architecture.md) | Internal code structure and request flow |
