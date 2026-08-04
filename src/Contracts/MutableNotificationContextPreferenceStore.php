<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\ValueObjects\NotificationContext;
use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;

interface MutableNotificationContextPreferenceStore extends NotificationContextPreferenceStore
{
    public function set(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
        bool $enabled,
        NotificationContextPreferenceMode $mode = NotificationContextPreferenceMode::DEFAULT,
    ): void;

    public function forget(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): void;
}
