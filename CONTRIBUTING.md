# Contributing to php-heijitu

Thank you for your interest in contributing!

## Prerequisites

- PHP 7.4 or 8.x
- [Docker](https://docs.docker.com/get-docker/) and Docker Compose (only needed for cross-version testing)
- [Composer](https://getcomposer.org/)

## Development Setup

```bash
git clone https://github.com/taku-o/php-heijitu.git
cd php-heijitu
composer install
```

## Running Tests

Run the full test suite (integration tests are excluded by default via `phpunit.xml`):

```bash
# Using locally installed PHP
vendor/bin/phpunit

# Using Docker (PHP 7.4)
docker compose -f docker/compose.yaml run --rm php74 vendor/bin/phpunit

# Using Docker (PHP 8.1)
docker compose -f docker/compose.yaml run --rm php81 vendor/bin/phpunit
```

Run integration tests (requires network access):

```bash
vendor/bin/phpunit --group integration
```

## Docker Environment

Two Docker environments are provided for cross-version testing:

| Service | PHP version | Command |
|---------|------------|---------|
| `php74` | PHP 7.4 | `docker compose -f docker/compose.yaml run --rm php74 <command>` |
| `php81` | PHP 8.1 | `docker compose -f docker/compose.yaml run --rm php81 <command>` |

Example:

```bash
# Run examples on PHP 8.1
docker compose -f docker/compose.yaml run --rm php81 php examples/main.php
```

## Coding Style

This project targets **PHP 7.4** as the minimum version. Follow these conventions:

- Declare `strict_types=1` in every PHP file.
- Use typed properties (PHP 7.4+) and return type declarations.
- Do **not** use union types (`int|string`), named arguments, or other PHP 8.0+ syntax.
- Format code with 4-space indentation.
- Class files follow PSR-4 naming: one class per file, file name matches class name.
- `final` classes are preferred for concrete implementations.

## Submitting a Pull Request

1. Fork the repository and create a feature branch from `master`.
2. Write tests for your changes. All tests must pass on both PHP 7.4 and PHP 8.1.
3. Ensure there are no PHPUnit failures or errors.
4. Open a pull request against `master` with a clear description of what you changed and why.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
