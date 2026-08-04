<?php

declare(strict_types=1);

namespace NotificationCompass\Stores;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use NotificationCompass\Contracts\MutableNotificationPreferenceStore;
use NotificationCompass\Contracts\InspectableNotificationPreferenceStore;
use NotificationCompass\Contracts\NotificationPreferenceCache;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Events\NotificationPreferenceChanged;
use NotificationCompass\Events\NotificationPreferenceChangeType;
use NotificationCompass\Models\NotificationPreference;
use NotificationCompass\ValueObjects\NotificationContext;

final class EloquentNotificationPreferenceStore implements MutableNotificationPreferenceStore, InspectableNotificationPreferenceStore
{
    private readonly ?NotificationPreferenceCache $cache;

    public function __construct(?NotificationPreferenceCache $cache = null)
    {
        $this->cache = $cache ?? $this->containerCache();
    }

    public function get(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): ?bool {
        $preference = NotificationPreference::query()
            ->where($this->identity($notifiable))
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->where('context_key', $this->contextKey($context))
            ->first();

        return $preference?->enabled;
    }

    public function set(
        object $notifiable,
        string $notificationKey,
        string $channel,
        bool $enabled,
        ?NotificationContext $context,
    ): void {
        $query = NotificationPreference::query()
            ->where($this->identity($notifiable))
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->where('context_key', $this->contextKey($context));
        $previous = $query->first();

        if ($previous?->enabled === $enabled) {
            return;
        }

        $definition = $this->definition($notificationKey);

        NotificationPreference::query()->updateOrCreate(
            [
                ...$this->identity($notifiable),
                'notification_key' => $notificationKey,
                'channel' => $channel,
                'context_key' => $this->contextKey($context),
            ],
            ['enabled' => $enabled],
        );
        $this->cache?->invalidateNotifiable($notifiable);
        event(new NotificationPreferenceChanged(
            notifiable: $notifiable,
            context: $context,
            definition: $definition,
            channel: $channel,
            oldValue: $previous?->enabled,
            newValue: $enabled,
            change: $previous === null
                ? NotificationPreferenceChangeType::CREATED
                : NotificationPreferenceChangeType::MODIFIED,
        ));
    }

    public function forget(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): void {
        $query = NotificationPreference::query()
            ->where($this->identity($notifiable))
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->where('context_key', $this->contextKey($context));
        $previous = $query->first();

        if ($previous === null) {
            return;
        }

        $definition = $this->definition($notificationKey);
        $query->delete();
        $this->cache?->invalidateNotifiable($notifiable);
        event(new NotificationPreferenceChanged(
            notifiable: $notifiable,
            context: $context,
            definition: $definition,
            channel: $channel,
            oldValue: $previous->enabled,
            newValue: null,
            change: NotificationPreferenceChangeType::RESET,
        ));
    }

    public function all(object $notifiable): array
    {
        return NotificationPreference::query()
            ->where($this->identity($notifiable))
            ->orderBy('notification_key')
            ->orderBy('channel')
            ->get(['notification_key', 'channel', 'context_key', 'enabled'])
            ->map(static fn (NotificationPreference $preference): array => [
                'notification_key' => $preference->notification_key,
                'channel' => $preference->channel,
                'context_key' => $preference->context_key,
                'enabled' => $preference->enabled,
            ])
            ->all();
    }

    private function identity(object $notifiable): array
    {
        if (! $notifiable instanceof Model || $notifiable->getKey() === null) {
            throw new InvalidArgumentException('The Eloquent preference store requires a persisted Eloquent notifiable model.');
        }

        return [
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => (string) $notifiable->getKey(),
        ];
    }

    private function contextKey(?NotificationContext $context): string
    {
        return $context?->key() ?? '';
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
}
