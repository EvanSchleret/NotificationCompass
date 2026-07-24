<?php

declare(strict_types=1);

namespace NotificationCompass\Tests\Unit;

use Illuminate\Notifications\Events\NotificationSending;
use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Listeners\NotificationSendingListener;
use NotificationCompass\Resolution\PreferenceResolver;
use NotificationCompass\Resolution\NotificationGate;
use NotificationCompass\ValueObjects\NotificationContext;
use PHPUnit\Framework\TestCase;

final class NotificationSendingListenerTest extends TestCase
{
    public function test_disabled_notification_is_stopped_before_delivery(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition(
            'message.received',
            ['mail'],
            notificationClass: TestNotification::class,
        ));

        $listener = new NotificationSendingListener(new NotificationGate(
            $registry,
            new TestContextResolver(),
            new TestPreferenceStore(false),
            new PreferenceResolver($registry, [], false),
        ));

        $allowed = $listener->handle(new NotificationSending(new TestNotifiable(), new TestNotification(), 'mail'));

        self::assertFalse($allowed);
    }

    public function test_unregistered_notification_is_not_intercepted(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $listener = new NotificationSendingListener(new NotificationGate(
            $registry,
            new TestContextResolver(),
            new TestPreferenceStore(false),
            new PreferenceResolver($registry, [], false),
        ));

        $allowed = $listener->handle(new NotificationSending(new TestNotifiable(), new TestNotification(), 'mail'));

        self::assertTrue($allowed);
    }

    public function test_undeclared_channels_are_not_delivered(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition(
            'message.received',
            ['database'],
            notificationClass: TestNotification::class,
        ));

        $listener = new NotificationSendingListener(new NotificationGate(
            $registry,
            new TestContextResolver(),
            new TestPreferenceStore(true),
            new PreferenceResolver($registry, [], false),
        ));

        $allowed = $listener->handle(new NotificationSending(new TestNotifiable(), new TestNotification(), 'mail'));

        self::assertFalse($allowed);
    }
}

final class TestNotification
{
}

final class TestNotifiable
{
}

final class TestContextResolver implements NotificationContextResolver
{
    public function resolve(object $notification, object $notifiable): ?NotificationContext
    {
        return null;
    }
}

final class TestPreferenceStore implements NotificationPreferenceStore
{
    public function __construct(private readonly ?bool $value)
    {
    }

    public function get(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): ?bool {
        return $this->value;
    }
}
