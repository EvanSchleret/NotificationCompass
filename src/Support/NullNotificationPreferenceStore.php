<?php

declare(strict_types=1);

namespace NotificationCompass\Support;

use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\ValueObjects\NotificationContext;

final class NullNotificationPreferenceStore implements NotificationPreferenceStore
{
    public function get(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): ?bool {
        return null;
    }
}
