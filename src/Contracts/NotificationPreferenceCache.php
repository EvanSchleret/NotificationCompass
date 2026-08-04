<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\Resolution\ResolvedPreference;
use NotificationCompass\ValueObjects\NotificationContext;

interface NotificationPreferenceCache
{
    public function get(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): ?ResolvedPreference;

    public function put(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
        ResolvedPreference $preference,
    ): void;

    public function invalidateNotifiable(object $notifiable): void;

    public function invalidateContext(NotificationContext $context): void;
}
