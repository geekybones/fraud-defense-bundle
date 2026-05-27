# Contributing

Thanks for taking the time to contribute to Fraud Defense Bundle.

## Before you start

- Search [open issues](https://github.com/geekybones/fraud-defense-bundle/issues) and [pull requests](https://github.com/geekybones/fraud-defense-bundle/pulls) to avoid duplicating work.
- For significant changes, open an issue first so we can discuss the approach before you invest time writing code.

## Requirements

- PHP 8.2+
- Composer

## Setup

```bash
git clone https://github.com/geekybones/fraud-defense-bundle.git
cd fraud-defense-bundle
composer install
```

## Running checks

```bash
# Tests
composer test

# Static analysis
composer phpstan

# Code style (check only)
vendor/bin/php-cs-fixer fix --dry-run --diff

# Code style (auto-fix)
vendor/bin/php-cs-fixer fix

# Rector (upgrade / refactor rules)
vendor/bin/rector process --dry-run
```

All four must pass before a pull request will be merged.

## Pull request guidelines

- **One concern per PR.** A bug fix and a new feature belong in separate PRs.
- **Tests required.** Add or update tests to cover your change. PRs that drop coverage without a good reason will not be accepted.
- **Follow existing style.** PHP-CS-Fixer and PHPStan configs are already in place — let them enforce style rather than debating it in review.
- **Update docs when needed.** If you change behaviour or add configuration options, update the relevant file in `docs/`.
- **Write a clear PR description.** Explain *what* changed and *why*. Link the relevant issue.

## Commit messages

Use the imperative mood and keep the subject line under 72 characters:

```
Add support for custom action prefix in RecaptchaType
Fix score threshold not applied when using checkbox key
```

## License

By contributing you agree that your changes will be released under the [MIT License](LICENSE) that covers this project.
