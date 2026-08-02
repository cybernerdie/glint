# Contributing to Glint

Thank you for considering a contribution to Glint!

## Pull Requests

- Fork the repository and create your branch from `main`.
- Run the full test suite before opening a PR: `composer test`
- Ensure PHPStan passes: `composer stan`
- Ensure code style passes: `composer pint`
- Write tests for any new behaviour.
- Keep PRs focused — one feature or fix per PR.

## Pricing Registry PRs

The easiest way to contribute is to add or update model pricing in `pricing/providers.json`. Prices are in **USD per 1 million tokens**.

```json
"anthropic": {
    "claude-3-5-sonnet-20241022": {
        "input": 3.00,
        "output": 15.00
    }
}
```

Please include a link to the official pricing page in your PR description so the values can be verified.

## Coding Standards

- PHP 8.3+, `declare(strict_types=1)` on every file.
- Follow PSR-12 and the project's existing conventions.
- All concrete classes must be `final`.
- Use constructor property promotion where possible.

## Running Tests Locally

```bash
composer install
composer test         # run the test suite
composer stan         # PHPStan level max
composer pint         # check code style (--test flag, no changes)
```

## Reporting Issues

Please use [GitHub Issues](https://github.com/cybernerdie/glint/issues). Include:

- Laravel and PHP version
- Glint version
- Steps to reproduce
- Expected vs actual behaviour
