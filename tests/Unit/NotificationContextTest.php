<?php

declare(strict_types=1);

namespace NotificationCompass\Tests\Unit;

use InvalidArgumentException;
use NotificationCompass\ValueObjects\NotificationContext;
use PHPUnit\Framework\TestCase;

final class NotificationContextTest extends TestCase
{
    public function test_numeric_string_identifiers_use_a_canonical_key(): void
    {
        $context = new NotificationContext('community', '01');
        $integerContext = new NotificationContext('community', 1);

        self::assertSame('1', $context->id);
        self::assertSame('community:1', $context->key());
        self::assertSame('community:1', $context->canonicalKey());
        self::assertSame($context->key(), $integerContext->key());
    }

    public function test_context_can_be_serialized_for_a_queued_notification(): void
    {
        $context = new NotificationContext('community', 42, ['name' => 'Acme']);
        $restored = unserialize(serialize($context));

        self::assertInstanceOf(NotificationContext::class, $restored);
        self::assertSame($context->toArray(), $restored->toArray());
        self::assertSame(
            $context->toArray(),
            json_decode((string) json_encode($context, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function test_invalid_context_types_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NotificationContext('Community', 1);
    }

    public function test_invalid_context_identifiers_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NotificationContext('community', 'community:1');
    }

    public function test_non_queue_safe_references_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NotificationContext::fromArray([
            'type' => 'community',
            'id' => 1,
            'reference' => new \stdClass(),
        ]);
    }
}
