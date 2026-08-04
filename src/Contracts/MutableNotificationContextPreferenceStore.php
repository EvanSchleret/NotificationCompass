<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\ValueObjects\NotificationContext;

interface MutableNotificationContextPreferenceStore extends NotificationContextPreferenceStore
{
    public function set(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
        bool $enabled,
    ): void;

    public function forget(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): void;
}
