<?php

declare(strict_types=1);

namespace NotificationCompass\Stores;

use Illuminate\Container\Container;
use NotificationCompass\Contracts\MutableNotificationContextPreferenceStore;
use NotificationCompass\Contracts\NotificationPreferenceCache;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Events\NotificationPreferenceChanged;
use NotificationCompass\Events\NotificationPreferenceChangeType;
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
        $query = NotificationContextPreferenceModel::query()
            ->where('context_key', $context->key())
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel);
        $previous = $query->first();

        if ($previous?->enabled === $enabled && $this->mode($previous) === $mode) {
            return;
        }

        $definition = $this->definition($notificationKey);

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
        event(new NotificationPreferenceChanged(
            notifiable: null,
            context: $context,
            definition: $definition,
            channel: $channel,
            oldValue: $previous?->enabled,
            newValue: $enabled,
            change: $previous === null
                ? NotificationPreferenceChangeType::CREATED
                : NotificationPreferenceChangeType::MODIFIED,
            oldMode: $previous === null ? null : $this->mode($previous),
            newMode: $mode,
        ));
    }

    public function forget(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): void {
        $query = NotificationContextPreferenceModel::query()
            ->where('context_key', $context->key())
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel);
        $previous = $query->first();

        if ($previous === null) {
            return;
        }

        $definition = $this->definition($notificationKey);
        $query->delete();
        $this->cache?->invalidateContext($context);
        event(new NotificationPreferenceChanged(
            notifiable: null,
            context: $context,
            definition: $definition,
            channel: $channel,
            oldValue: $previous->enabled,
            newValue: null,
            change: NotificationPreferenceChangeType::DELETED,
            oldMode: $this->mode($previous),
        ));
    }

    private function containerCache(): ?NotificationPreferenceCache
    {
        $container = Container::getInstance();

        return $container->bound(NotificationPreferenceCache::class)
            ? $container->make(NotificationPreferenceCache::class)
            : null;
    }

    private function definition(string $notificationKey): NotificationDefinition
    {
        return Container::getInstance()
            ->make(NotificationDefinitionRegistry::class)
            ->get($notificationKey);
    }

    private function mode(NotificationContextPreferenceModel $preference): NotificationContextPreferenceMode
    {
        $mode = $preference->getAttribute('mode');

        return $mode instanceof NotificationContextPreferenceMode
            ? $mode
            : NotificationContextPreferenceMode::from((string) $mode);
    }
}
