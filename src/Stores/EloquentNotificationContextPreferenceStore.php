<?php

declare(strict_types=1);

namespace NotificationCompass\Stores;

use NotificationCompass\Contracts\MutableNotificationContextPreferenceStore;
use NotificationCompass\Models\NotificationContextPreference;
use NotificationCompass\ValueObjects\NotificationContext;

final class EloquentNotificationContextPreferenceStore implements MutableNotificationContextPreferenceStore
{
    public function get(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): ?bool {
        $preference = NotificationContextPreference::query()
            ->where('context_key', $context->key())
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->first();

        return $preference?->enabled;
    }

    public function set(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
        bool $enabled,
    ): void {
        NotificationContextPreference::query()->updateOrCreate(
            [
                'context_key' => $context->key(),
                'notification_key' => $notificationKey,
                'channel' => $channel,
            ],
            ['enabled' => $enabled],
        );
    }

    public function forget(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): void {
        NotificationContextPreference::query()
            ->where('context_key', $context->key())
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->delete();
    }
}
