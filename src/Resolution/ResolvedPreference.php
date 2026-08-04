<?php

declare(strict_types=1);

namespace NotificationCompass\Resolution;

use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;

final readonly class ResolvedPreference
{
    public function __construct(
        public bool $enabled,
        public string $source,
        public bool $mandatory = false,
        public ?NotificationContextPreferenceMode $mode = null,
    ) {
    }

    public function isModifiable(): bool
    {
        return ! $this->mandatory && $this->mode !== NotificationContextPreferenceMode::ENFORCED;
    }
}
