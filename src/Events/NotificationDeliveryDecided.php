<?php

declare(strict_types=1);

namespace NotificationCompass\Events;

use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Resolution\ResolvedPreference;
use NotificationCompass\ValueObjects\NotificationContext;

final readonly class NotificationDeliveryDecided
{
    public function __construct(
        public object $notifiable,
        public object $notification,
        public string $channel,
        public ?NotificationContext $context,
        public ?NotificationDefinition $definition,
        public ResolvedPreference $preference,
        public bool $customized = false,
        public ?bool $originalEnabled = null,
    ) {
    }
}
