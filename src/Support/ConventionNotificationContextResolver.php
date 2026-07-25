<?php

declare(strict_types=1);

namespace NotificationCompass\Support;

use InvalidArgumentException;
use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\ValueObjects\NotificationContext;

final class ConventionNotificationContextResolver implements NotificationContextResolver
{
    public function resolve(object $notification, object $notifiable): ?NotificationContext
    {
        if (! method_exists($notification, 'notificationContext')) {
            return null;
        }

        $context = $notification->notificationContext($notifiable);
        if ($context !== null && ! $context instanceof NotificationContext) {
            throw new InvalidArgumentException(
                'The notificationContext method must return a NotificationContext or null.',
            );
        }

        return $context;
    }
}
