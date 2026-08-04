<?php

declare(strict_types=1);

namespace NotificationCompass\ValueObjects;

final readonly class NotificationChannelMetadata
{
    public function __construct(
        public ?string $label = null,
        public ?string $description = null,
        public bool $visible = true,
    ) {
    }

    public static function fromConfig(array $metadata): self
    {
        return new self(
            label: is_string($metadata['label'] ?? null) ? $metadata['label'] : null,
            description: is_string($metadata['description'] ?? null) ? $metadata['description'] : null,
            visible: (bool) ($metadata['visible'] ?? true),
        );
    }
}
