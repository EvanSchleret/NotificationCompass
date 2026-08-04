<?php

declare(strict_types=1);

namespace NotificationCompass\ValueObjects;

use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Resolution\ResolvedPreference;

final readonly class NotificationPreferenceInspection
{
    public function __construct(
        public NotificationDefinition $definition,
        public string $channel,
        public bool $enabled,
        public string $source,
        public bool $modifiable,
        public bool $mandatory,
        public ?NotificationContextPreferenceMode $mode = null,
    ) {
    }

    public static function fromResolved(
        NotificationDefinition $definition,
        string $channel,
        ResolvedPreference $preference,
    ): self {
        return new self(
            definition: $definition,
            channel: $channel,
            enabled: $preference->enabled,
            source: $preference->source,
            modifiable: $preference->isModifiable(),
            mandatory: $preference->mandatory,
            mode: $preference->mode,
        );
    }

    public function isModifiable(): bool
    {
        return $this->modifiable;
    }
}
