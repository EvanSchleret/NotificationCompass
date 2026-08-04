<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\ValueObjects\NotificationContext;

interface NotificationContextPreferenceStore
{
    public function get(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): ?bool;
}
