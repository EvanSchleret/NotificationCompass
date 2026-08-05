<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\ValueObjects\NotificationContext;

interface InspectableNotificationPreferenceStore extends NotificationPreferenceStore
{
    public function all(object $notifiable, ?NotificationContext $context = null): array;
}
