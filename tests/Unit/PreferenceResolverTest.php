<?php

declare(strict_types=1);

namespace NotificationCompass\Tests\Unit;

use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Contracts\NotificationContextPreferenceStore;
use NotificationCompass\Contracts\NotificationPreferenceCache;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Resolution\PreferenceResolver;
use NotificationCompass\Resolution\ResolvedPreference;
use NotificationCompass\ValueObjects\NotificationContext;
use NotificationCompass\ValueObjects\NotificationContextPreference;
use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;
use PHPUnit\Framework\TestCase;

final class PreferenceResolverTest extends TestCase
{
    public function test_mandatory_rules_win_over_user_preferences(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition('security.alert', ['mail'], mandatoryChannels: ['mail']));

        $result = $this->resolver($registry)->resolve(
            new class {},
            'security.alert',
            'mail',
            null,
            new InMemoryPreferenceStore(false),
        );

        self::assertTrue($result->enabled);
        self::assertSame('mandatory', $result->source);
        self::assertFalse($result->isModifiable());
    }

    public function test_context_preferences_win_over_global_preferences_and_defaults(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition(
            'event.booking_created',
            ['mail'],
            defaults: ['mail' => false],
            contextDefaults: ['organization:42' => ['mail' => true]],
        ));

        $result = $this->resolver($registry)->resolve(
            new class {},
            'event.booking_created',
            'mail',
            new NotificationContext('organization', 42),
            new InMemoryPreferenceStore(false, true),
        );

        self::assertTrue($result->enabled);
        self::assertSame('user_context', $result->source);
    }

    public function test_context_policies_win_over_user_preferences(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition(
            'event.booking_created',
            ['mail'],
            defaults: ['mail' => true],
        ));

        $result = (new PreferenceResolver(
            $registry,
            ['mail' => true],
            false,
            new InMemoryContextPreferenceStore(false, NotificationContextPreferenceMode::ENFORCED),
        ))->resolve(
            new class {},
            'event.booking_created',
            'mail',
            new NotificationContext('organization', 42),
            new InMemoryPreferenceStore(true, true),
        );

        self::assertFalse($result->enabled);
        self::assertSame('context_policy', $result->source);
        self::assertFalse($result->isModifiable());
    }

    public function test_default_context_policies_can_be_overridden_by_user_preferences(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition('event.booking_created', ['mail']));

        $result = (new PreferenceResolver(
            $registry,
            ['mail' => true],
            false,
            new InMemoryContextPreferenceStore(false),
        ))->resolve(
            new class {},
            'event.booking_created',
            'mail',
            new NotificationContext('organization', 42),
            new InMemoryPreferenceStore(true, true),
        );

        self::assertTrue($result->enabled);
        self::assertSame('user_context', $result->source);
        self::assertNull($result->mode);
        self::assertTrue($result->isModifiable());
    }

    public function test_default_context_policy_is_used_after_user_preferences(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition(
            'event.booking_created',
            ['mail'],
            defaults: ['mail' => true],
        ));

        $result = (new PreferenceResolver(
            $registry,
            ['mail' => true],
            false,
            new InMemoryContextPreferenceStore(false),
        ))->resolve(
            new class {},
            'event.booking_created',
            'mail',
            new NotificationContext('organization', 42),
            new InMemoryPreferenceStore(),
        );

        self::assertFalse($result->enabled);
        self::assertSame('context_policy', $result->source);
        self::assertSame(NotificationContextPreferenceMode::DEFAULT, $result->mode);
        self::assertTrue($result->isModifiable());
    }

    public function test_opt_in_notifications_are_disabled_without_an_explicit_default(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition('digest.weekly', ['mail'], optIn: true));

        $result = $this->resolver($registry)->resolve(
            new class {},
            'digest.weekly',
            'mail',
            null,
            new InMemoryPreferenceStore(),
        );

        self::assertFalse($result->enabled);
        self::assertSame('opt_in_default', $result->source);
    }

    public function test_resolved_preferences_are_read_from_cache_before_store_resolution(): void
    {
        $registry = new NotificationDefinitionRegistry();
        $registry->register(new NotificationDefinition('event.booking_created', ['mail']));
        $cached = new ResolvedPreference(false, 'cached');
        $cache = new InMemoryPreferenceCache($cached);

        $result = (new PreferenceResolver(
            $registry,
            ['mail' => true],
            false,
            null,
            $cache,
        ))->resolve(
            new class {},
            'event.booking_created',
            'mail',
            new NotificationContext('organization', 42),
            new InMemoryPreferenceStore(true, true),
        );

        self::assertSame($cached, $result);
        self::assertFalse($cache->wasWritten);
    }

    private function resolver(NotificationDefinitionRegistry $registry): PreferenceResolver
    {
        return new PreferenceResolver($registry, ['mail' => true], false);
    }
}

final readonly class InMemoryPreferenceStore implements NotificationPreferenceStore
{
    public function __construct(
        private ?bool $global = null,
        private ?bool $context = null,
    ) {
    }

    public function get(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): ?bool {
        return $context === null ? $this->global : $this->context;
    }
}

final readonly class InMemoryContextPreferenceStore implements NotificationContextPreferenceStore
{
    public function __construct(
        private ?bool $value,
        private NotificationContextPreferenceMode $mode = NotificationContextPreferenceMode::DEFAULT,
    )
    {
    }

    public function get(
        NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): ?NotificationContextPreference {
        return $this->value === null
            ? null
            : new NotificationContextPreference($this->value, $this->mode);
    }
}

final class InMemoryPreferenceCache implements NotificationPreferenceCache
{
    public bool $wasWritten = false;

    public function __construct(private ?ResolvedPreference $value = null)
    {
    }

    public function get(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
    ): ?ResolvedPreference {
        return $this->value;
    }

    public function put(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
        ResolvedPreference $preference,
    ): void {
        $this->wasWritten = true;
        $this->value = $preference;
    }

    public function invalidateNotifiable(object $notifiable): void
    {
    }

    public function invalidateContext(NotificationContext $context): void
    {
    }
}
