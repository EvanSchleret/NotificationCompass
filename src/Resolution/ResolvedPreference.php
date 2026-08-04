<?php

declare(strict_types=1);

namespace NotificationCompass\Resolution;

use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;

final readonly class ResolvedPreference
{
    public readonly string $source;

    public function __construct(
        public bool $enabled,
        public NotificationDecisionReason $reason,
        public bool $mandatory = false,
        public ?NotificationContextPreferenceMode $mode = null,
    ) {
        $this->source = $reason->value;
    }

    public function isModifiable(): bool
    {
        return ! $this->mandatory && $this->mode !== NotificationContextPreferenceMode::ENFORCED;
    }

    public function isDefault(): bool
    {
        return $this->reason->isDefault();
    }
}
