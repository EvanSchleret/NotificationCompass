<?php

declare(strict_types=1);

namespace NotificationCompass\ValueObjects;

final readonly class NotificationDefinitionMetadata
{
    public function __construct(
        public ?string $label = null,
        public ?string $description = null,
        public ?string $category = null,
        public int $order = 0,
    ) {
    }

    public static function fromConfig(array $attributes): self
    {
        $metadata = is_array($attributes['metadata'] ?? null)
            ? $attributes['metadata']
            : [];

        return new self(
            label: self::stringValue($metadata['label'] ?? null),
            description: self::stringValue($metadata['description'] ?? null),
            category: self::stringValue($metadata['category'] ?? null),
            order: self::integerValue(
                $metadata['order'] ?? 0,
            ),
        );
    }

    private static function stringValue(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function integerValue(mixed $value): int
    {
        return is_int($value) || is_numeric($value) ? (int) $value : 0;
    }
}
