<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\ValueObjects\NotificationContext;

interface NotificationPreferenceStore
{
    public function get(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): ?bool;
}
