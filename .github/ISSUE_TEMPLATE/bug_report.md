---
name: Bug report
about: Something is not working as expected
labels: bug
assignees: ''
---

## Description

A clear description of the bug.

## Steps to reproduce

1. Configure the bundle with ...
2. Add `RecaptchaType` to a form ...
3. Submit the form ...
4. See error

## Expected behavior

What you expected to happen.

## Actual behavior

What actually happened. Include full exception messages and stack traces if applicable.

## Environment

| | |
|---|---|
| PHP | e.g. 8.3.x |
| Symfony | e.g. 7.2.x |
| `geekybones/fraud-defense-bundle` | e.g. 1.0.1 |
| reCAPTCHA key type | score / checkbox |
| Feature in use | standard / transaction_defense / sms_defense |

## Configuration

```yaml
# config/packages/fraud_defense.yaml (remove sensitive values)
fraud_defense:
    project_id: '%env(FRAUD_DEFENSE_PROJECT_ID)%'
    ...
```

## Additional context

Anything else that might help: logs, screenshots, related issues.
