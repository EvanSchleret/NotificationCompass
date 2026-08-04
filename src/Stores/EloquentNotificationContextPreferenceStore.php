<?php

declare(strict_types=1);

namespace NotificationCompass\Stores;

use NotificationCompass\Contracts\MutableNotificationContextPreferenceStore;
use NotificationCompass\Models\NotificationContextPreference as NotificationContextPreferenceModel;
use NotificationCompass\ValueObjects\NotificationContext;
use NotificationCompass\ValueObjects\NotificationContextPreference;
use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;

final class EloquentNotificationContextPreferenceStore implements MutableNotificationContextPreferenceStore
{
    public function get(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): ?NotificationContextPreference {
        $preference = NotificationContextPreferenceModel::query()
            ->where('context_key', $context->key())
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->first();

        return $preference === null
            ? null
            : new NotificationContextPreference($preference->enabled, $preference->mode);
    }

    public function set(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
        bool $enabled,
        NotificationContextPreferenceMode $mode = NotificationContextPreferenceMode::DEFAULT,
    ): void {
        NotificationContextPreferenceModel::query()->updateOrCreate(
            [
                'context_key' => $context->key(),
                'notification_key' => $notificationKey,
                'channel' => $channel,
            ],
            [
                'enabled' => $enabled,
                'mode' => $mode->value,
            ],
        );
    }

    public function forget(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): void {
        NotificationContextPreferenceModel::query()
            ->where('context_key', $context->key())
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->delete();
    }
}
