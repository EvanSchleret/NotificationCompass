# Contributing to NotificationCompass

Thank you for your interest in contributing to NotificationCompass.

NotificationCompass is a Laravel package for resolving whether a notification should be sent to a user, through a channel, and in an optional business context. Keep contributions focused on notification preferences and compatible with Laravel's native notification system.

Please open an issue before substantial changes so the scope can be discussed.

## Prerequisites

The project is intended for PHP and Laravel applications and is distributed through Composer. Use versions compatible with the package requirements once they are defined in `composer.json`.

## Development setup

1. Fork the repository and clone your fork.
2. Create a branch for your change.
3. Install dependencies with `composer install` when the package definition is available.
4. Make the smallest change that addresses the issue.

## Tests and quality checks

Run the repository's documented test, lint, and static-analysis commands before opening a pull request. If a command is not yet documented, describe the checks you ran in the pull request.

Behavior changes should include or update tests, especially for preference resolution, context isolation, queued notifications, and mandatory rules.

## Pull requests

Pull requests should:

- Explain the problem and the proposed solution.
- Include tests for behavior changes.
- Update documentation when the public API changes.
- Call out breaking changes and migration requirements.
- Keep unrelated refactoring out of the change.

Use clear commit messages and keep each pull request reviewable.

## Questions

Open a [GitHub issue](https://github.com/EvanSchleret/NotificationCompass/issues) for questions, proposals, or bug reports.

## Code of Conduct

By participating in this project, you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).
