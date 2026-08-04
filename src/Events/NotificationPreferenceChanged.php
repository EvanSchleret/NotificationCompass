<?php

declare(strict_types=1);

namespace NotificationCompass\Events;

use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\ValueObjects\NotificationContext;
use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;

final readonly class NotificationPreferenceChanged
{
    public function __construct(
        public ?object $notifiable,
        public ?NotificationContext $context,
        public NotificationDefinition $definition,
        public string $channel,
        public ?bool $oldValue,
        public ?bool $newValue,
        public NotificationPreferenceChangeType $change,
        public ?NotificationContextPreferenceMode $oldMode = null,
        public ?NotificationContextPreferenceMode $newMode = null,
    ) {
    }
}
