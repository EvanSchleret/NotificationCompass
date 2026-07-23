<?php

declare(strict_types=1);

namespace NotificationCompass\Definitions;

use InvalidArgumentException;

final class NotificationDefinitionRegistry
{
    /** @var array<string, NotificationDefinition> */
    private array $definitions = [];

    public function register(NotificationDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new InvalidArgumentException("Notification type [{$definition->key}] is already registered.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function get(string $key): NotificationDefinition
    {
        return $this->definitions[$key]
            ?? throw new InvalidArgumentException("Notification type [{$key}] is not registered.");
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function forNotification(object $notification): ?NotificationDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->notificationClass !== null && $notification instanceof $definition->notificationClass) {
                return $definition;
            }
        }

        return null;
    }

    /** @return array<string, NotificationDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }
}
