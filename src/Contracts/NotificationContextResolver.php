<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\ValueObjects\NotificationContext;

interface NotificationContextResolver
{
    public function resolve(object $notification, object $notifiable): ?NotificationContext;
}
