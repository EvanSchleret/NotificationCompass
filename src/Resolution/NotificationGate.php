<?php

declare(strict_types=1);

namespace NotificationCompass\Resolution;

use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\ValueObjects\NotificationContext;

final readonly class NotificationGate
{
    public function __construct(
        private NotificationDefinitionRegistry $definitions,
        private NotificationContextResolver $contexts,
        private NotificationPreferenceStore $preferences,
        private PreferenceResolver $resolver,
    ) {
    }

    public function decision(
        object $notifiable,
        object $notification,
        string $channel,
        ?NotificationContext $context = null,
    ): ?ResolvedPreference {
        $definition = $this->definitions->forNotification($notification);
        if ($definition === null) {
            return null;
        }

        if (! $definition->hasChannel($channel)) {
            return new ResolvedPreference(false, 'channel_unavailable');
        }

        $resolvedContext = $context ?? $this->contexts->resolve($notification, $notifiable);
        if (! $definition->supportsContext($resolvedContext)) {
            return new ResolvedPreference(false, 'context_unavailable');
        }

        return $this->resolver->resolve(
            $notifiable,
            $definition->key,
            $channel,
            $resolvedContext,
            $this->preferences,
        );
    }

    public function allows(
        object $notifiable,
        object $notification,
        string $channel,
        ?NotificationContext $context = null,
    ): bool {
        return $this->decision($notifiable, $notification, $channel, $context)?->enabled ?? true;
    }
}
