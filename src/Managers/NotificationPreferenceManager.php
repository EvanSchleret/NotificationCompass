<?php

declare(strict_types=1);

namespace NotificationCompass\Managers;

use NotificationCompass\Contracts\MutableNotificationPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Resolution\PreferenceResolver;
use NotificationCompass\Resolution\ResolvedPreference;
use NotificationCompass\ValueObjects\NotificationContext;
use InvalidArgumentException;
use LogicException;

final readonly class NotificationPreferenceManager
{
    public function __construct(
        private object $notifiable,
        private MutableNotificationPreferenceStore $store,
        private PreferenceResolver $resolver,
        private NotificationDefinitionRegistry $definitions,
    ) {
    }

    public function enable(
        string $notificationKey,
        string $channel,
        ?NotificationContext $context = null,
    ): void {
        $this->assertModifiable($notificationKey, $channel);
        $this->store->set($this->notifiable, $notificationKey, $channel, true, $context);
    }

    public function for(
        string $notificationKey,
        ?NotificationContext $context = null,
    ): NotificationPreferenceSelection {
        return new NotificationPreferenceSelection($this, $notificationKey, $context);
    }

    public function definition(string $notificationKey): NotificationDefinition
    {
        return $this->definitions->get($notificationKey);
    }

    public function definitions(): array
    {
        return $this->definitions->all();
    }

    public function disable(
        string $notificationKey,
        string $channel,
        ?NotificationContext $context = null,
    ): void {
        $this->assertModifiable($notificationKey, $channel);
        $this->store->set($this->notifiable, $notificationKey, $channel, false, $context);
    }

    public function reset(
        string $notificationKey,
        string $channel,
        ?NotificationContext $context = null,
    ): void {
        $this->assertModifiable($notificationKey, $channel);
        $this->store->forget($this->notifiable, $notificationKey, $channel, $context);
    }

    public function explicit(
        string $notificationKey,
        string $channel,
        ?NotificationContext $context = null,
    ): ?bool {
        return $this->store->get($this->notifiable, $notificationKey, $channel, $context);
    }

    public function effective(
        string $notificationKey,
        string $channel,
        ?NotificationContext $context = null,
    ): ResolvedPreference {
        return $this->resolver->resolve(
            $this->notifiable,
            $notificationKey,
            $channel,
            $context,
            $this->store,
        );
    }

    private function assertModifiable(string $notificationKey, string $channel): void
    {
        $definition = $this->definition($notificationKey);

        if (! $definition->hasChannel($channel)) {
            throw new InvalidArgumentException(
                "Channel [{$channel}] is not available for notification type [{$notificationKey}].",
            );
        }

        if (! $definition->isModifiableFor($channel)) {
            throw new LogicException(
                "Channel [{$channel}] cannot be modified for notification type [{$notificationKey}].",
            );
        }
    }
}
