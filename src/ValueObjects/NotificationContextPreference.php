<?php

declare(strict_types=1);

namespace NotificationCompass\ValueObjects;

final readonly class NotificationContextPreference
{
    public function __construct(
        public bool $enabled,
        public NotificationContextPreferenceMode $mode = NotificationContextPreferenceMode::DEFAULT,
    ) {
    }
}
