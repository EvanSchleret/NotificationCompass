<?php

declare(strict_types=1);

namespace NotificationCompass\ValueObjects;

final readonly class NotificationContext
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
}
