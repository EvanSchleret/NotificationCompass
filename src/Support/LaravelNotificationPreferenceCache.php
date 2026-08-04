<?php

declare(strict_types=1);

namespace NotificationCompass\Support;

use Illuminate\Contracts\Cache\Repository;
use NotificationCompass\Contracts\NotificationPreferenceCache;
use NotificationCompass\Resolution\ResolvedPreference;
use NotificationCompass\ValueObjects\NotificationContext;

final class LaravelNotificationPreferenceCache implements NotificationPreferenceCache
{
    public function __construct(
        private Repository $cache,
        private int $ttl,
        private string $prefix,
    ) {
    }

    public function get(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): ?ResolvedPreference {
        if ($this->ttl <= 0) {
            return null;
        }

        $preference = $this->cache->get($this->key($notifiable, $notificationKey, $channel, $context));

        return $preference instanceof ResolvedPreference ? $preference : null;
    }

    public function put(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
        ResolvedPreference $preference,
    ): void {
        if ($this->ttl <= 0) {
            return;
        }

        $this->cache->put(
            $this->key($notifiable, $notificationKey, $channel, $context),
            $preference,
            $this->ttl,
        );
    }

    public function invalidateNotifiable(object $notifiable): void
    {
        $this->cache->forever($this->notifiableVersionKey($notifiable), $this->version());
    }

    public function invalidateContext(NotificationContext $context): void
    {
        $this->cache->forever($this->contextVersionKey($context), $this->version());
    }

    private function key(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): string {
        $notifiableVersion = $this->versionValue($this->notifiableVersionKey($notifiable));
        $contextVersion = $context === null ? 'global' : $this->versionValue($this->contextVersionKey($context));

        return implode(':', [
            $this->prefix,
            rawurlencode($this->notifiableKey($notifiable)),
            rawurlencode($notificationKey),
            rawurlencode($channel),
            rawurlencode($context?->key() ?? 'global'),
            rawurlencode($notifiableVersion),
            rawurlencode($contextVersion),
        ]);
    }

    private function notifiableVersionKey(object $notifiable): string
    {
        return $this->prefix . ':version:notifiable:' . rawurlencode($this->notifiableKey($notifiable));
    }

    private function contextVersionKey(NotificationContext $context): string
    {
        return $this->prefix . ':version:context:' . rawurlencode($context->key());
    }

    private function versionValue(string $key): string
    {
        $version = $this->cache->get($key, '0');

        return is_string($version) ? $version : '0';
    }

    private function version(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function notifiableKey(object $notifiable): string
    {
        $type = method_exists($notifiable, 'getMorphClass')
            ? (string) $notifiable->getMorphClass()
            : $notifiable::class;
        $id = method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null;

        return $type . ':' . ($id === null ? (string) spl_object_id($notifiable) : (string) $id);
    }
}
