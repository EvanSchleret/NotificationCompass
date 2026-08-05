# NotificationCompass

NotificationCompass is a Laravel package for managing notification preferences above Laravel's native notification system.

It helps applications decide whether a notification should be sent to a user, through a channel, and in an optional business context. It supports global and context-specific preferences, defaults, opt-in notifications, and mandatory notifications while remaining independent from application-specific domain models.

## Status

NotificationCompass follows the v1.x API contract. Breaking changes are reserved for a future major release.

## Installation

Install the latest v1.x release with Composer:

```bash
composer require evanschleret/notificationcompass
```

To test unreleased changes, configure the repository and require the `dev-main` branch explicitly:

```bash
composer config repositories.notificationcompass vcs https://github.com/EvanSchleret/NotificationCompass
composer require evanschleret/notificationcompass:dev-main
```

## Documentation

Read the [NotificationCompass documentation](https://notificationcompass.schleret.ch) for installation, concepts, configuration, API reference, and integration guides.

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening an issue or pull request.

## Security

Please report vulnerabilities privately. See [SECURITY.md](SECURITY.md) for the reporting process.

## License

NotificationCompass is released under the [MIT License](LICENSE).
