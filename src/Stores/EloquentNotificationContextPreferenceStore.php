<?php

declare(strict_types=1);

namespace NotificationCompass\Stores;

use Illuminate\Container\Container;
use NotificationCompass\Contracts\MutableNotificationContextPreferenceStore;
use NotificationCompass\Contracts\NotificationPreferenceCache;
use NotificationCompass\Models\NotificationContextPreference as NotificationContextPreferenceModel;
use NotificationCompass\ValueObjects\NotificationContext;
use NotificationCompass\ValueObjects\NotificationContextPreference;
use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;

final class EloquentNotificationContextPreferenceStore implements MutableNotificationContextPreferenceStore
{
    private readonly ?NotificationPreferenceCache $cache;

    public function __construct(?NotificationPreferenceCache $cache = null)
    {
        $this->cache = $cache ?? $this->containerCache();
    }

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
        $this->cache?->invalidateContext($context);
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
        $this->cache?->invalidateContext($context);
    }

    private function containerCache(): ?NotificationPreferenceCache
    {
        $container = Container::getInstance();

        return $container->bound(NotificationPreferenceCache::class)
            ? $container->make(NotificationPreferenceCache::class)
            : null;
    }
}
