<?php

declare(strict_types=1);

namespace NotificationCompass\Support;

use NotificationCompass\Contracts\NotificationPreferenceCache;
use NotificationCompass\Resolution\ResolvedPreference;
use NotificationCompass\ValueObjects\NotificationContext;

final class NullNotificationPreferenceCache implements NotificationPreferenceCache
{
    public function get(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): ?ResolvedPreference {
        return null;
    }

    public function put(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
        ResolvedPreference $preference,
    ): void {
    }

    public function invalidateNotifiable(object $notifiable): void
    {
    }

    public function invalidateContext(NotificationContext $context): void
    {
    }
}
