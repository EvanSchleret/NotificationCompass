<?php

declare(strict_types=1);

namespace NotificationCompass\Stores;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use NotificationCompass\Contracts\MutableNotificationPreferenceStore;
use NotificationCompass\Models\NotificationPreference;
use NotificationCompass\ValueObjects\NotificationContext;

final class EloquentNotificationPreferenceStore implements MutableNotificationPreferenceStore
{
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
        NotificationPreference::query()->updateOrCreate(
            [
                ...$this->identity($notifiable),
                'notification_key' => $notificationKey,
                'channel' => $channel,
                'context_key' => $this->contextKey($context),
            ],
            ['enabled' => $enabled],
        );
    }

    public function forget(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): void {
        NotificationPreference::query()
            ->where($this->identity($notifiable))
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->where('context_key', $this->contextKey($context))
            ->delete();
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
}
