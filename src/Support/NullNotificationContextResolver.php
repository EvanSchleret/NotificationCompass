<?php

declare(strict_types=1);

namespace NotificationCompass\Support;

use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\ValueObjects\NotificationContext;

final class NullNotificationContextResolver implements NotificationContextResolver
{
    public function resolve(object $notification, object $notifiable): ?NotificationContext
    {
        return null;
    }
}
