# Configuration

File: `config/packages/fraud_defense.yaml`

---

## Full reference

```yaml
fraud_defense:
    project_id: '%env(FRAUD_DEFENSE_PROJECT_ID)%'   # required
    api_key:    '%env(FRAUD_DEFENSE_API_KEY)%'       # required
    site_key:   '%env(FRAUD_DEFENSE_SITE_KEY)%'      # required; score (invisible) site key

    checkbox_site_key: null        # required only when using type: checkbox
    min_score:         0.5         # 0.0–1.0; default pass threshold for RecaptchaType
    expected_hostname: null        # e.g. www.example.com; validates token hostname
    fail_on_api_error: true        # false = allow form submit when Google API is unreachable

    transaction_defense:
        enabled:              false
        max_transaction_risk: 0.7  # block when transactionRisk >= this (0.0–1.0)

    sms_defense:
        enabled:      false
        max_sms_risk: 0.5          # block when smsRisk >= this (0.0–1.0)
```

---

## Environment variables

| Variable | Required | Description |
|----------|----------|-------------|
| `FRAUD_DEFENSE_PROJECT_ID` | Yes | Google Cloud project ID |
| `FRAUD_DEFENSE_API_KEY` | Yes | reCAPTCHA Enterprise API key |
| `FRAUD_DEFENSE_SITE_KEY` | Yes | Score (invisible) site key |
| `FRAUD_DEFENSE_CHECKBOX_SITE_KEY` | Only for checkbox | Checkbox site key |

Use [Symfony secrets](https://symfony.com/doc/current/configuration/secrets.html) in production rather than plain env vars.

---

## Recipes

### Standard bot protection (default)

`RecaptchaType` works with root-level config only. No feature flags required.

```yaml
fraud_defense:
    project_id: '%env(FRAUD_DEFENSE_PROJECT_ID)%'
    api_key:    '%env(FRAUD_DEFENSE_API_KEY)%'
    site_key:   '%env(FRAUD_DEFENSE_SITE_KEY)%'
    min_score:  0.5
```

### Checkbox widget

```yaml
fraud_defense:
    site_key:          '%env(FRAUD_DEFENSE_SITE_KEY)%'
    checkbox_site_key: '%env(FRAUD_DEFENSE_CHECKBOX_SITE_KEY)%'
```

Set `'type' => 'checkbox'` on the form field. See [recaptcha.md](recaptcha.md).

### Hostname validation

```yaml
fraud_defense:
    expected_hostname: 'www.example.com'
```

The token's hostname must match exactly. Applies to all enabled defenses. Leave `null` to skip the check (useful in local development).

### Allow forms when Google API is down

```yaml
fraud_defense:
    fail_on_api_error: false
```

When `false`, assessment failures are logged as warnings and the form is allowed through. You can also override this per form field: `'fail_on_api_error' => false`.

### Load the reCAPTCHA script once from your layout

By default each field renders its own `<script>` tag. To load it once from a base template instead:

```php
// In your form type
$builder->add('captcha', RecaptchaType::class, [
    'load_script' => false,
]);
```

```twig
{# In your base layout — match the type used by your form fields #}
{{ recaptcha_script('score') }}
{# or for checkbox fields: #}
{{ recaptcha_script('checkbox') }}
```

Works with all three form types. The `type` must match between the Twig helper, the form field, and the backend assessment.

### Transaction Defense

```yaml
fraud_defense:
    project_id: '%env(FRAUD_DEFENSE_PROJECT_ID)%'
    api_key:    '%env(FRAUD_DEFENSE_API_KEY)%'
    site_key:   '%env(FRAUD_DEFENSE_SITE_KEY)%'
    transaction_defense:
        enabled:              true
        max_transaction_risk: 0.7
```

See [transaction-defense.md](transaction-defense.md) for GCP console setup, which must be completed before `fraudPreventionAssessment` appears in API responses.

### SMS Defense

```yaml
fraud_defense:
    project_id: '%env(FRAUD_DEFENSE_PROJECT_ID)%'
    api_key:    '%env(FRAUD_DEFENSE_API_KEY)%'
    site_key:   '%env(FRAUD_DEFENSE_SITE_KEY)%'
    sms_defense:
        enabled:      true
        max_sms_risk: 0.5
```

See [sms-defense.md](sms-defense.md) for GCP key configuration required before `phoneFraudAssessment` appears in API responses.
