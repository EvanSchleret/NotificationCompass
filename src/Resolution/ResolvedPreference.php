<?php

declare(strict_types=1);

namespace NotificationCompass\Resolution;

final readonly class ResolvedPreference
{
    public function __construct(
        public bool $enabled,
        public string $source,
        public bool $mandatory = false,
    ) {
    }

    public function isModifiable(): bool
    {
        return ! $this->mandatory;
    }
}
