# Architecture

Internal code structure and request flow. Intended for contributors and for developers who need to extend the bundle.

---

## Request flow

```
Form submit / Controller call
        |
        v
  RecaptchaType / TransactionRecaptchaType / SmsRecaptchaType
  (POST_SUBMIT listener)
        |
        | assess(token, action, context?, ...)
        v
  RecaptchaAssessment / TransactionAssessment / SmsAssessment   [Defense/Service/]
        |
        | 1. Resolve site key (SiteKeyResolver)
        | 2. Build AssessmentEventContext
        | 3. Call event builder -> Google Event
        v
  AssessmentClient::create(Event)   [Assessment/]
        |
        | CreateAssessmentRequest -> Google gRPC
        | AssessmentTokenValidator rejects invalid/expired tokens
        v
  Google Assessment (protobuf)
        |
        v
  *AssessmentMapper::map(Assessment, ...)   [Defense/Mapper/]
        |
        | AssessmentPassEvaluator: score/risk vs threshold, action, hostname
        v
  *AssessmentResult   [Defense/Model/]
        |
        v
  hasPassed() -> form error or return to caller
```

---

## Source layout

```
src/
|
+-- Assessment/                 Google API client layer
|   +-- Exception/
|   |   +-- AssessmentException.php      Base exception; wraps Google API errors
|   +-- AssessmentClient.php             Calls createAssessment; validates token
|   +-- AssessmentClientInterface.php
|   +-- AssessmentTokenValidator.php     Rejects EXPIRED/INVALID/DUPE tokens
|
+-- Client/                     gRPC transport
|   +-- EnterpriseClientFactory.php      Creates and caches RecaptchaEnterpriseServiceClient
|
+-- Http/                       HTTP utilities
|   +-- ClientIpResolver.php             Reads X-Forwarded-For / X-Real-Ip / CF-Connecting-IP
|
+-- Defense/                    Fraud logic (public API surface)
|   +-- Contract/
|   |   +-- RecaptchaAssessmentInterface.php
|   |   +-- TransactionAssessmentInterface.php
|   |   +-- SmsAssessmentInterface.php
|   +-- Event/                  Build Google Event protos from context
|   |   +-- RecaptchaAssessmentEvent.php
|   |   +-- TransactionAssessmentEvent.php
|   |   +-- SmsAssessmentEvent.php
|   +-- Mapper/                 Map Google Assessment proto -> *AssessmentResult
|   |   +-- RecaptchaAssessmentMapper.php
|   |   +-- TransactionAssessmentMapper.php
|   |   +-- SmsAssessmentMapper.php
|   |   +-- GoogleTransactionDataMapper.php
|   +-- Model/                  DTOs (inputs and outputs)
|   |   +-- RecaptchaAssessmentResult.php
|   |   +-- TransactionAssessmentContext.php
|   |   +-- TransactionAssessmentResult.php
|   |   +-- TransactionUser.php
|   |   +-- TransactionAddress.php
|   |   +-- SmsAssessmentContext.php
|   |   +-- SmsAssessmentResult.php
|   +-- Service/                Orchestration (implement the interfaces)
|   |   +-- RecaptchaAssessment.php
|   |   +-- TransactionAssessment.php
|   |   +-- SmsAssessment.php
|   +-- AssessmentEventContext.php       Token + site key + action + HTTP request
|   +-- AssessmentPassEvaluator.php      passesScore() / passesRiskBelow()
|   +-- AssessmentSerializer.php         Assessment proto -> PHP array (injectable)
|   +-- AssessmentTokenProperties.php    Normalized tokenProperties from the response
|   +-- DefenseAssessmentException.php   Wraps AssessmentException; shown on forms
|   +-- RecaptchaTwigExtension.php       recaptcha_script() Twig function
|   +-- SiteKeyResolver.php              Resolves score/checkbox site key by type
|   +-- TransactionAssessmentContextValidator.php
|
+-- DependencyInjection/
|   +-- Configuration.php        YAML tree builder
|   +-- FraudDefenseExtension.php  Registers services; wires optional features
|
+-- Form/
|   +-- AbstractRecaptchaFormType.php   Shared widget rendering + base options
|   +-- RecaptchaType.php
|   +-- TransactionRecaptchaType.php
|   +-- SmsRecaptchaType.php
|
+-- FraudDefenseBundle.php
```

---

## Pass/fail logic

### Score check (`RecaptchaType`)

```
passesScore(tokenValid, score, minScore, responseAction, expectedAction, responseHostname, expectedHostname)

  tokenValid == false  -> fail
  expectedAction != '' && responseAction != expectedAction  -> fail
  expectedHostname set && responseHostname != expectedHostname  -> fail
  score < minScore  -> fail   (score == minScore passes)
  otherwise  -> pass
```

### Risk check (`TransactionRecaptchaType`, `SmsRecaptchaType`)

```
passesRiskBelow(tokenValid, risk, maxRisk, ...)

  same token/action/hostname checks as above
  risk >= maxRisk  -> fail   (strict: equal to threshold blocks)
  otherwise  -> pass
```

For `TransactionRecaptchaType` with `check_score: true`, a passing risk check is then additionally tested against `min_score`. If the reCAPTCHA score is below the threshold, the assessment fails.

---

## Dependency injection

Services are auto-discovered from `src/` via `config/services.yaml`. Scalar parameters are bound explicitly by `FraudDefenseExtension`.

| Interface | Implementation | Registered |
|-----------|---------------|------------|
| `RecaptchaAssessmentInterface` | `RecaptchaAssessment` | Always |
| `TransactionAssessmentInterface` | `TransactionAssessment` | `transaction_defense.enabled: true` only |
| `SmsAssessmentInterface` | `SmsAssessment` | `sms_defense.enabled: true` only |

`TransactionRecaptchaType` and `SmsRecaptchaType` are always registered as form types, but their assessment service argument is wired to `null` when the feature is disabled. Submitting the form without the feature enabled throws a `LogicException`.

---

## Form widget

All three form types extend `AbstractRecaptchaFormType`, which:

- Sets `getParent()` to `HiddenType` (the field is a hidden input)
- Uses `getBlockPrefix() = 'recaptcha'` to select the shared Twig block
- Registers `POST_SUBMIT` to run the assessment on form submit
- Exposes `buildView()` variables: `site_key`, `action`, `recaptcha_type`, `load_script`

The form theme (`@FraudDefense/recaptcha.html.twig`) is auto-prepended to `twig.form_themes` via `PrependExtensionInterface`. It renders the hidden input and, when `load_script` is `true`, inlines the `<script>` tag.

---

## AssessmentSerializer

`AssessmentSerializer` converts a Google `Assessment` protobuf to a plain PHP array via `serializeToJsonString()` + `json_decode()`. It accepts an optional `LoggerInterface`; serialization failures are logged as warnings and return `[]` rather than throwing. Injected into all three mappers so `*AssessmentResult::getRaw()` is always populated.
