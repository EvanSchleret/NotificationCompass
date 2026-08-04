<?php

declare(strict_types=1);

namespace NotificationCompass\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Throwable;

final readonly class NotificationContext implements JsonSerializable
{
    public readonly string $type;
    public readonly string|int $id;
    public readonly array|bool|float|int|string|null $reference;

    public function __construct(
        string $type,
        string|int $id,
        array|bool|float|int|string|null $reference = null,
    ) {
        if (preg_match('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/D', $type) !== 1) {
            throw new InvalidArgumentException(
                'A notification context type must use lowercase segments separated by dots, hyphens, or underscores.',
            );
        }

        $this->type = $type;
        $this->id = self::normalizeId($id);
        self::assertSerializable($reference);
        $this->reference = $reference;
    }

    public function key(): string
    {
        return $this->canonicalKey();
    }

    public function canonicalKey(): string
    {
        return $this->type . ':' . (string) $this->id;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'reference' => $this->reference,
            'key' => $this->canonicalKey(),
        ];
    }

    public function __serialize(): array
    {
        return $this->toArray();
    }

    public function __unserialize(array $data): void
    {
        $context = self::fromArray($data);

        $this->type = $context->type;
        $this->id = $context->id;
        $this->reference = $context->reference;
    }

    public static function fromArray(array $value): self
    {
        if (! isset($value['type'], $value['id']) || ! is_string($value['type'])) {
            throw new InvalidArgumentException('A notification context requires a valid string type and identifier.');
        }

        if (! is_string($value['id']) && ! is_int($value['id'])) {
            throw new InvalidArgumentException('A notification context identifier must be a string or integer.');
        }

        $reference = $value['reference'] ?? null;
        self::assertSerializable($reference);

        return new self($value['type'], $value['id'], $reference);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function normalizeId(string|int $id): string|int
    {
        if (is_int($id)) {
            if ($id < 0) {
                throw new InvalidArgumentException('A notification context identifier cannot be negative.');
            }

            return $id;
        }

        if (preg_match('/\A[0-9A-Za-z][0-9A-Za-z._~-]*\z/D', $id) !== 1) {
            throw new InvalidArgumentException(
                'A notification context identifier must contain only letters, numbers, dots, hyphens, underscores, or tildes.',
            );
        }

        if (ctype_digit($id)) {
            return ltrim($id, '0') ?: '0';
        }

        return $id;
    }

    private static function assertSerializable(mixed $value): void
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                'A notification context reference must contain only queue-safe scalar values or arrays.',
                previous: $exception,
            );
        }

        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                self::assertSerializable($nestedValue);
            }

            return;
        }

        if (is_null($value) || is_bool($value) || is_int($value) || is_string($value)) {
            return;
        }

        if (is_float($value) && is_finite($value)) {
            return;
        }

        throw new InvalidArgumentException(
            'A notification context reference must contain only queue-safe scalar values or arrays.',
        );
    }
}
