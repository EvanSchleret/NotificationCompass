<?php

declare(strict_types=1);

namespace NotificationCompass\Concerns;

use NotificationCompass\Contracts\MutableNotificationPreferenceStore;
use NotificationCompass\Managers\NotificationPreferenceManager;
use NotificationCompass\Resolution\PreferenceResolver;

trait HasNotificationPreferences
{
    public function notificationPreferences(): NotificationPreferenceManager
    {
        return new NotificationPreferenceManager(
            $this,
            app(MutableNotificationPreferenceStore::class),
            app(PreferenceResolver::class),
        );
    }

    public function enableNotification(
        string $notificationKey,
        string $channel,
        ?\NotificationCompass\ValueObjects\NotificationContext $context = null,
    ): void {
        $this->notificationPreferences()->enable($notificationKey, $channel, $context);
    }

    public function disableNotification(
        string $notificationKey,
        string $channel,
        ?\NotificationCompass\ValueObjects\NotificationContext $context = null,
    ): void {
        $this->notificationPreferences()->disable($notificationKey, $channel, $context);
    }

    public function resetNotificationPreference(
        string $notificationKey,
        string $channel,
        ?\NotificationCompass\ValueObjects\NotificationContext $context = null,
    ): void {
        $this->notificationPreferences()->reset($notificationKey, $channel, $context);
    }
}
