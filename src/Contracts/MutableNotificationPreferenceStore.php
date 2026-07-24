<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\ValueObjects\NotificationContext;

interface MutableNotificationPreferenceStore extends NotificationPreferenceStore
{
    public function set(
        object $notifiable,
        string $notificationKey,
        string $channel,
        bool $enabled,
        ?NotificationContext $context,
    ): void;

    public function forget(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): void;
}
