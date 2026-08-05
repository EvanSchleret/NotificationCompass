<?php

declare(strict_types=1);

namespace NotificationCompass\Tests\Unit;

use Illuminate\Notifications\Events\NotificationSending;
use LogicException;
use NotificationCompass\Contracts\NotificationContextAuthorizer;
use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Listeners\NotificationSendingListener;
use NotificationCompass\Resolution\NotificationDecisionReason;
use NotificationCompass\Resolution\PreferenceResolver;
use NotificationCompass\Resolution\NotificationGate;
use NotificationCompass\Resolution\UnknownNotificationBehavior;
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
            new TestContextAuthorizer(),
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
            new TestContextAuthorizer(),
        ));

        $allowed = $listener->handle(new NotificationSending(new TestNotifiable(), new TestNotification(), 'mail'));

        self::assertTrue($allowed);
    }

    public function test_unknown_notifications_can_be_denied_explicitly(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $gate = new NotificationGate(
            $registry,
            new TestContextResolver(),
            new TestPreferenceStore(true),
            new PreferenceResolver($registry, [], false),
            new TestContextAuthorizer(),
            UnknownNotificationBehavior::DENY,
        );

        $result = $gate->decision(new TestNotifiable(), new TestNotification(), 'mail');

        self::assertNotNull($result);
        self::assertFalse($result->enabled);
        self::assertSame(NotificationDecisionReason::UNKNOWN_NOTIFICATION, $result->reason);
        self::assertSame('unknown_notification', $result->source);
    }

    public function test_unknown_notifications_can_throw_explicitly(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $gate = new NotificationGate(
            $registry,
            new TestContextResolver(),
            new TestPreferenceStore(true),
            new PreferenceResolver($registry, [], false),
            new TestContextAuthorizer(),
            UnknownNotificationBehavior::THROW_EXCEPTION,
        );

        $this->expectException(LogicException::class);
        $gate->decision(new TestNotifiable(), new TestNotification(), 'mail');
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
            new TestContextAuthorizer(),
        ));

        $allowed = $listener->handle(new NotificationSending(new TestNotifiable(), new TestNotification(), 'mail'));

        self::assertFalse($allowed);
    }

    public function test_unauthorized_contexts_are_not_delivered(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition(
            'message.received',
            ['mail'],
            notificationClass: TestNotification::class,
        ));

        $gate = new NotificationGate(
            $registry,
            new TestContextResolver(),
            new TestPreferenceStore(true),
            new PreferenceResolver($registry, [], false),
            new DenyingContextAuthorizer(),
        );

        $result = $gate->decision(
            new TestNotifiable(),
            new TestNotification(),
            'mail',
            new NotificationContext('organization', 42),
        );

        self::assertNotNull($result);
        self::assertFalse($result->enabled);
        self::assertSame(NotificationDecisionReason::CONTEXT_UNAUTHORIZED, $result->reason);
        self::assertSame('context_unauthorized', $result->source);
    }

    public function test_notifications_requiring_context_are_not_delivered_without_one(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition(
            'message.received',
            ['mail'],
            notificationClass: TestNotification::class,
            requiresContext: true,
        ));

        $gate = new NotificationGate(
            $registry,
            new TestContextResolver(),
            new TestPreferenceStore(true),
            new PreferenceResolver($registry, [], false),
            new TestContextAuthorizer(),
        );

        $result = $gate->decision(
            new TestNotifiable(),
            new TestNotification(),
            'mail',
        );

        self::assertNotNull($result);
        self::assertFalse($result->enabled);
        self::assertSame(NotificationDecisionReason::CONTEXT_REQUIRED, $result->reason);
        self::assertSame('context_required', $result->source);
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

final class TestContextAuthorizer implements NotificationContextAuthorizer
{
    public function authorize(object $notifiable, NotificationContext $context): bool
    {
        return true;
    }
}

final class DenyingContextAuthorizer implements NotificationContextAuthorizer
{
    public function authorize(object $notifiable, NotificationContext $context): bool
    {
        return false;
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
