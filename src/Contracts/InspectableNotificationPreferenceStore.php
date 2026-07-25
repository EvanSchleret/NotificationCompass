<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

interface InspectableNotificationPreferenceStore extends NotificationPreferenceStore
{
    public function all(object $notifiable): array;
}
