<?php

declare(strict_types=1);

namespace NotificationCompass\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class NotificationContext implements JsonSerializable
{
    public function __construct(
        public string $type,
        public string|int $id,
        public array|bool|float|int|object|string|null $reference = null,
    ) {
    }

    public function key(): string
    {
        return $this->type . ':' . (string) $this->id;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'reference' => $this->reference,
        ];
    }

    public static function fromArray(array $value): self
    {
        if (! isset($value['type'], $value['id']) || ! is_string($value['type'])) {
            throw new InvalidArgumentException('A notification context requires a string type and an identifier.');
        }

        if (! is_string($value['id']) && ! is_int($value['id'])) {
            throw new InvalidArgumentException('A notification context identifier must be a string or integer.');
        }

        return new self($value['type'], $value['id'], $value['reference'] ?? null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
