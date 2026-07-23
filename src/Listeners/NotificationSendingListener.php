<?php

declare(strict_types=1);

namespace NotificationCompass\Listeners;

use Illuminate\Notifications\Events\NotificationSending;
use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Resolution\PreferenceResolver;

final readonly class NotificationSendingListener
{
    public function __construct(
        private NotificationDefinitionRegistry $definitions,
        private NotificationContextResolver $contexts,
        private NotificationPreferenceStore $preferences,
        private PreferenceResolver $resolver,
    ) {
    }

    public function handle(NotificationSending $event): bool
    {
        $definition = $this->definitions->forNotification($event->notification);
        if ($definition === null) {
            return true;
        }

        $context = $this->contexts->resolve($event->notification, $event->notifiable);
        $preference = $this->resolver->resolve(
            $event->notifiable,
            $definition->key,
            $event->channel,
            $context,
            $this->preferences,
        );

        return $preference->enabled;
    }
}
