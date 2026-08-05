<?php

declare(strict_types=1);

namespace NotificationCompass\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Notifications\Factory as NotificationFactory;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use NotificationCompass\Concerns\HasNotificationPreferences;
use NotificationCompass\Contracts\NotificationContextAuthorizer;
use NotificationCompass\Contracts\MutableNotificationContextPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Contracts\NotificationDefinitionProvider;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Events\NotificationPreferenceChanged;
use NotificationCompass\Events\NotificationPreferenceChangeType;
use NotificationCompass\Managers\NotificationContextPreferenceManager;
use NotificationCompass\NotificationCompassServiceProvider;
use NotificationCompass\Resolution\NotificationDecisionReason;
use NotificationCompass\Stores\EloquentNotificationContextPreferenceStore;
use NotificationCompass\Stores\EloquentNotificationPreferenceStore;
use NotificationCompass\ValueObjects\NotificationContext;
use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;
use Orchestra\Testbench\TestCase;
use InvalidArgumentException;
use LogicException;

final class EloquentNotificationPreferenceStoreTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NotificationCompassServiceProvider::class];
    }

    protected function getApplicationProviders($app): array
    {
        return [
            ...parent::getApplicationProviders($app),
            NotificationServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('notificationcompass.definitions', [
            'event.booking_created' => [
                'channels' => ['mail', 'database'],
                'defaults' => ['database' => true],
                'metadata' => [
                    'label' => 'Booking created',
                    'description' => 'Notifications about newly created bookings.',
                    'category' => 'activity',
                    'order' => 20,
                ],
                'channel_metadata' => [
                    'mail' => [
                        'label' => 'Email',
                        'description' => 'Send the notification by email.',
                    ],
                    'database' => ['visible' => false],
                ],
                'notification_class' => TestConfiguredNotification::class,
            ],
            'security.alert' => [
                'channels' => ['mail', 'database'],
                'mandatory_channels' => ['mail'],
                'channel_options' => [
                    'database' => ['default' => true],
                ],
                'channel_metadata' => [
                    'database' => ['visible' => false],
                ],
            ],
            'event.contextual' => [
                'channels' => ['mail'],
                'notification_class' => TestContextualNotification::class,
                'supported_contexts' => ['organization'],
            ],
            'test.sendable' => [
                'channels' => ['test'],
                'notification_class' => TestSendableNotification::class,
            ],
            'test.multi' => [
                'channels' => ['test', 'test_secondary'],
                'notification_class' => TestMultiChannelNotification::class,
            ],
            'test.database' => [
                'channels' => ['database'],
                'notification_class' => TestDatabaseNotification::class,
            ],
            'test.broadcast' => [
                'channels' => ['broadcast'],
                'notification_class' => TestBroadcastNotification::class,
            ],
        ]);
        $app['config']->set('notificationcompass.definition_providers', [TestDefinitionProvider::class]);
        $app['config']->set('queue.default', 'sync');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        (new NotificationServiceProvider($this->app))->register();
        $this->app->make(NotificationFactory::class)->extend('test', static fn (): TestChannel => new TestChannel());
        $this->app->make(NotificationFactory::class)->extend('test_secondary', static fn (): TestChannel => new TestChannel());
        TestChannel::$sent = 0;
    }

    public function test_context_preferences_are_isolated_and_resettable(): void
    {
        $user = TestUser::query()->create();
        $store = new EloquentNotificationPreferenceStore();
        $organization = new NotificationContext('organization', 10);
        $project = new NotificationContext('project', 10);

        $store->set($user, 'event.booking_created', 'mail', false, $organization);

        self::assertFalse($store->get($user, 'event.booking_created', 'mail', $organization));
        self::assertNull($store->get($user, 'event.booking_created', 'mail', $project));

        $store->forget($user, 'event.booking_created', 'mail', $organization);

        self::assertNull($store->get($user, 'event.booking_created', 'mail', $organization));

        $user->notificationPreferences()
            ->for('event.booking_created', $project)
            ->enable('mail');

        self::assertTrue($user->notificationPreferences()
            ->for('event.booking_created', $project)
            ->explicit('mail'));

        self::assertFalse($user->canReceiveNotification(new TestConfiguredNotification(), 'mail'));
        $user->enableNotification('event.booking_created', 'mail');
        self::assertTrue($user->canReceiveNotification(new TestConfiguredNotification(), 'mail'));
    }

    public function test_context_preferences_can_apply_a_policy_to_all_members(): void
    {
        $context = new NotificationContext('organization', 10);
        $store = new EloquentNotificationContextPreferenceStore();
        $store->set(
            $context,
            'event.contextual',
            'mail',
            false,
            NotificationContextPreferenceMode::ENFORCED,
        );

        $user = TestUser::query()->create();
        $user->enableNotification('event.contextual', 'mail');

        self::assertFalse($user->canReceiveNotification(
            new TestContextualNotification(),
            'mail',
            $context,
        ));
        $preference = $store->get($context, 'event.contextual', 'mail');
        self::assertNotNull($preference);
        self::assertFalse($preference->enabled);
        self::assertSame(NotificationContextPreferenceMode::ENFORCED, $preference->mode);

        $store->forget($context, 'event.contextual', 'mail');

        self::assertNull($store->get($context, 'event.contextual', 'mail'));
        self::assertTrue($user->canReceiveNotification(
            new TestContextualNotification(),
            'mail',
            $context,
        ));
    }

    public function test_context_policy_manager_handles_policy_lifecycle_and_inspection(): void
    {
        $manager = $this->app->make(NotificationContextPreferenceManager::class);
        $context = new NotificationContext('organization', 10);

        self::assertNull($manager->get($context, 'event.contextual', 'mail'));

        $manager->disable(
            $context,
            'event.contextual',
            'mail',
            NotificationContextPreferenceMode::DEFAULT,
        );

        $policy = $manager->get($context, 'event.contextual', 'mail');
        self::assertNotNull($policy);
        self::assertFalse($policy->enabled);
        self::assertSame(NotificationContextPreferenceMode::DEFAULT, $policy->mode);

        $inspection = $manager->inspect($context);
        $contextPolicy = $inspection['event.contextual']['mail'];
        self::assertTrue($contextPolicy->isConfigured());
        self::assertTrue($contextPolicy->modifiable);
        self::assertFalse($contextPolicy->enabled);
        self::assertSame(NotificationContextPreferenceMode::DEFAULT, $contextPolicy->mode);

        $manager->enable(
            $context,
            'event.contextual',
            'mail',
            NotificationContextPreferenceMode::ENFORCED,
        );

        self::assertTrue($manager->get($context, 'event.contextual', 'mail')?->enabled);
        self::assertSame(
            NotificationContextPreferenceMode::ENFORCED,
            $manager->inspect($context)['event.contextual']['mail']->mode,
        );

        $manager->reset($context, 'event.contextual', 'mail');

        self::assertNull($manager->get($context, 'event.contextual', 'mail'));
        self::assertFalse($manager->inspect($context)['event.contextual']['mail']->isConfigured());
    }

    public function test_context_policy_manager_rejects_undeclared_channels(): void
    {
        $manager = $this->app->make(NotificationContextPreferenceManager::class);

        $this->expectException(InvalidArgumentException::class);
        $manager->set(
            new NotificationContext('organization', 10),
            'event.contextual',
            'database',
            true,
        );
    }

    public function test_context_policy_manager_rejects_unsupported_contexts(): void
    {
        $manager = $this->app->make(NotificationContextPreferenceManager::class);

        $this->expectException(InvalidArgumentException::class);
        $manager->set(
            new NotificationContext('project', 10),
            'event.contextual',
            'mail',
            true,
        );
    }

    public function test_context_policy_manager_rejects_mandatory_channels(): void
    {
        $manager = $this->app->make(NotificationContextPreferenceManager::class);

        $this->expectException(LogicException::class);
        $manager->set(
            new NotificationContext('organization', 10),
            'security.alert',
            'mail',
            true,
        );
    }

    public function test_context_preferences_are_isolated_by_context_key(): void
    {
        $store = new EloquentNotificationContextPreferenceStore();
        $organization = new NotificationContext('organization', 10);
        $team = new NotificationContext('team', 10);

        $store->set($organization, 'event.contextual', 'mail', false);

        self::assertFalse($store->get($organization, 'event.contextual', 'mail')?->enabled);
        self::assertNull($store->get($team, 'event.contextual', 'mail'));
    }

    public function test_resolved_preference_cache_is_invalidated_after_context_policy_changes(): void
    {
        $this->app['config']->set('notificationcompass.cache.enabled', true);
        $user = TestUser::query()->create();
        $user->enableNotification('event.contextual', 'mail');
        $context = new NotificationContext('organization', 10);
        $store = $this->app->make(MutableNotificationContextPreferenceStore::class);

        self::assertTrue($user->canReceiveNotification(
            new TestContextualNotification(),
            'mail',
            $context,
        ));

        $store->set(
            $context,
            'event.contextual',
            'mail',
            false,
            NotificationContextPreferenceMode::ENFORCED,
        );

        self::assertFalse($user->canReceiveNotification(
            new TestContextualNotification(),
            'mail',
            $context,
        ));

        $store->forget($context, 'event.contextual', 'mail');

        self::assertTrue($user->canReceiveNotification(
            new TestContextualNotification(),
            'mail',
            $context,
        ));
    }

    public function test_cached_user_preferences_are_isolated_between_contexts_and_invalidated_on_change(): void
    {
        $this->app['config']->set('notificationcompass.cache.enabled', true);
        $user = TestUser::query()->create();
        $organization = new NotificationContext('organization', 10);
        $team = new NotificationContext('team', 10);
        $notification = new TestConfiguredNotification();

        $user->enableNotification('event.booking_created', 'mail');

        self::assertTrue($user->canReceiveNotification($notification, 'mail', $organization));
        self::assertTrue($user->canReceiveNotification($notification, 'mail', $team));

        $user->disableNotification('event.booking_created', 'mail', $organization);

        self::assertFalse($user->canReceiveNotification($notification, 'mail', $organization));
        self::assertTrue($user->canReceiveNotification($notification, 'mail', $team));

        $user->disableNotification('event.booking_created', 'mail');

        self::assertFalse($user->canReceiveNotification($notification, 'mail', $team));
    }

    public function test_contextual_preferences_override_global_preferences_without_leaking_to_other_contexts(): void
    {
        $user = TestUser::query()->create();
        $organization = new NotificationContext('organization', 10);
        $team = new NotificationContext('team', 10);
        $notification = new TestConfiguredNotification();

        $user->disableNotification('event.booking_created', 'mail');
        $user->enableNotification('event.booking_created', 'mail', $organization);

        self::assertTrue($user->canReceiveNotification($notification, 'mail', $organization));
        self::assertFalse($user->canReceiveNotification($notification, 'mail', $team));
    }

    public function test_enforced_context_policy_overrides_user_preferences_and_reset_restores_them(): void
    {
        $this->app['config']->set('notificationcompass.cache.enabled', false);
        $user = TestUser::query()->create();
        $organization = new NotificationContext('organization', 10);
        $otherOrganization = new NotificationContext('organization', 11);
        $notification = new TestContextualNotification();
        $store = $this->app->make(MutableNotificationContextPreferenceStore::class);

        $user->enableNotification('event.contextual', 'mail');
        $store->set(
            $organization,
            'event.contextual',
            'mail',
            false,
            NotificationContextPreferenceMode::ENFORCED,
        );

        self::assertFalse($user->canReceiveNotification($notification, 'mail', $organization));
        self::assertTrue($user->canReceiveNotification($notification, 'mail', $otherOrganization));

        $store->forget($organization, 'event.contextual', 'mail');

        self::assertTrue($user->canReceiveNotification($notification, 'mail', $organization));
    }

    public function test_missing_context_does_not_apply_a_contextual_preference(): void
    {
        $user = TestUser::query()->create();
        $organization = new NotificationContext('organization', 10);
        $notification = new TestConfiguredNotification();

        $user->enableNotification('event.booking_created', 'mail');
        $user->disableNotification('event.booking_created', 'mail', $organization);

        self::assertFalse($user->canReceiveNotification($notification, 'mail', $organization));
        self::assertTrue($user->canReceiveNotification($notification, 'mail'));
    }

    public function test_unauthorized_context_blocks_real_notification_delivery(): void
    {
        $this->app->instance(NotificationContextAuthorizer::class, new DenyingFeatureContextAuthorizer());
        $user = TestUser::query()->create();

        $result = $this->app['events']->dispatch(
            new NotificationSending($user, new TestContextualNotification(), 'mail'),
            [],
            true,
        );

        self::assertFalse($result);
    }

    public function test_user_preference_changes_dispatch_events_with_previous_and_new_values(): void
    {
        Event::fake();
        $user = TestUser::query()->create();
        $store = new EloquentNotificationPreferenceStore();

        $store->set($user, 'event.booking_created', 'mail', true, null);
        $store->set($user, 'event.booking_created', 'mail', false, null);
        $store->forget($user, 'event.booking_created', 'mail', null);

        Event::assertDispatchedTimes(NotificationPreferenceChanged::class, 3);
        Event::assertDispatched(NotificationPreferenceChanged::class, static function (
            NotificationPreferenceChanged $event,
        ): bool {
            return $event->change === NotificationPreferenceChangeType::CREATED
                && $event->notifiable instanceof TestUser
                && $event->context === null
                && $event->definition->key === 'event.booking_created'
                && $event->channel === 'mail'
                && $event->oldValue === null
                && $event->newValue === true;
        });
        Event::assertDispatched(NotificationPreferenceChanged::class, static function (
            NotificationPreferenceChanged $event,
        ): bool {
            return $event->change === NotificationPreferenceChangeType::MODIFIED
                && $event->oldValue === true
                && $event->newValue === false;
        });
        Event::assertDispatched(NotificationPreferenceChanged::class, static function (
            NotificationPreferenceChanged $event,
        ): bool {
            return $event->change === NotificationPreferenceChangeType::RESET
                && $event->oldValue === false
                && $event->newValue === null;
        });
    }

    public function test_context_policy_changes_dispatch_events_with_modes(): void
    {
        Event::fake();
        $context = new NotificationContext('organization', 10);
        $store = new EloquentNotificationContextPreferenceStore();

        $store->set(
            $context,
            'event.contextual',
            'mail',
            false,
            NotificationContextPreferenceMode::DEFAULT,
        );
        $store->set(
            $context,
            'event.contextual',
            'mail',
            true,
            NotificationContextPreferenceMode::ENFORCED,
        );
        $store->forget($context, 'event.contextual', 'mail');

        Event::assertDispatchedTimes(NotificationPreferenceChanged::class, 3);
        Event::assertDispatched(NotificationPreferenceChanged::class, static function (
            NotificationPreferenceChanged $event,
        ): bool {
            return $event->change === NotificationPreferenceChangeType::CREATED
                && $event->notifiable === null
                && $event->context?->key() === 'organization:10'
                && $event->oldValue === null
                && $event->newValue === false
                && $event->oldMode === null
                && $event->newMode === NotificationContextPreferenceMode::DEFAULT;
        });
        Event::assertDispatched(NotificationPreferenceChanged::class, static function (
            NotificationPreferenceChanged $event,
        ): bool {
            return $event->change === NotificationPreferenceChangeType::MODIFIED
                && $event->oldValue === false
                && $event->newValue === true
                && $event->oldMode === NotificationContextPreferenceMode::DEFAULT
                && $event->newMode === NotificationContextPreferenceMode::ENFORCED;
        });
        Event::assertDispatched(NotificationPreferenceChanged::class, static function (
            NotificationPreferenceChanged $event,
        ): bool {
            return $event->change === NotificationPreferenceChangeType::DELETED
                && $event->oldValue === true
                && $event->newValue === null
                && $event->oldMode === NotificationContextPreferenceMode::ENFORCED
                && $event->newMode === null;
        });
    }

    public function test_definitions_are_loaded_from_configuration(): void
    {
        $definition = $this->app->make(NotificationDefinitionRegistry::class)
            ->get('event.booking_created');

        self::assertSame(['mail', 'database'], $definition->channels);
        self::assertSame(TestConfiguredNotification::class, $definition->notificationClass);
        self::assertTrue($this->app->make(NotificationDefinitionRegistry::class)->has('digest.weekly'));
    }

    public function test_definition_metadata_is_structured_and_channel_visibility_is_descriptive(): void
    {
        $definition = $this->app->make(NotificationDefinitionRegistry::class)
            ->get('event.booking_created');

        self::assertSame('Booking created', $definition->metadata->label);
        self::assertSame('Notifications about newly created bookings.', $definition->metadata->description);
        self::assertSame('activity', $definition->metadata->category);
        self::assertSame(20, $definition->metadata->order);
        self::assertSame('Email', $definition->channelMetadata('mail')->label);
        self::assertTrue($definition->channelMetadata('mail')->visible);
        self::assertFalse($definition->channelMetadata('database')->visible);
        self::assertTrue($definition->isHidden('database'));
    }

    public function test_inspection_api_exposes_channels_and_rules(): void
    {
        $user = TestUser::query()->create();
        $selection = $user->notificationPreferences()->for('security.alert');

        self::assertSame(['mail', 'database'], $selection->channels());
        self::assertTrue($selection->isMandatory('mail'));
        self::assertFalse($selection->isModifiable('mail'));
        self::assertFalse($selection->isMandatory('database'));
        self::assertTrue($selection->isModifiable('database'));
        self::assertTrue($selection->isHidden('database'));
    }

    public function test_notification_context_can_round_trip_through_arrays_and_json(): void
    {
        $context = new NotificationContext('organization', 42, ['name' => 'Acme']);
        $restored = NotificationContext::fromArray($context->toArray());

        self::assertSame('organization:42', $restored->key());
        self::assertSame($context->toArray(), json_decode((string) json_encode($context), true));
    }

    public function test_user_cannot_write_unknown_or_mandatory_channels(): void
    {
        $user = TestUser::query()->create();

        $this->expectException(LogicException::class);
        $user->enableNotification('security.alert', 'mail');
    }

    public function test_user_cannot_write_an_undeclared_channel(): void
    {
        $user = TestUser::query()->create();

        $this->expectException(InvalidArgumentException::class);
        $user->enableNotification('security.alert', 'push');
    }

    public function test_context_is_resolved_from_the_notification_convention(): void
    {
        $user = TestUser::query()->create();
        $context = new NotificationContext('organization', 7);
        $store = new EloquentNotificationPreferenceStore();
        $store->set($user, 'event.booking_created', 'mail', false, $context);

        self::assertFalse($user->canReceiveNotification(new TestContextualNotification(), 'mail'));
    }

    public function test_explicit_and_effective_preferences_are_inspectable(): void
    {
        $user = TestUser::query()->create();
        $user->disableNotification('event.booking_created', 'mail');

        $explicit = $user->notificationPreferences()->explicitPreferences();
        $effective = $user->notificationPreferences()->effectivePreferences();

        self::assertSame(false, $explicit[0]['enabled']);
        self::assertFalse($effective['event.booking_created']['mail']->enabled);
        self::assertSame('user_global', $effective['event.booking_created']['mail']->source);
    }

    public function test_contextual_inspection_exposes_definition_channel_and_resolution_metadata(): void
    {
        $user = TestUser::query()->create();
        $context = new NotificationContext('organization', 10);
        $user->enableNotification('event.contextual', 'mail', $context);

        $inspection = $user->notificationPreferences()->inspectPreferences($context);
        $userContext = $inspection['event.contextual']['mail'];

        self::assertSame('event.contextual', $userContext->definition->key);
        self::assertSame(['mail'], $userContext->definition->channels);
        self::assertSame('mail', $userContext->channel);
        self::assertTrue($userContext->enabled);
        self::assertSame(NotificationDecisionReason::USER_CONTEXT, $userContext->reason);
        self::assertSame('user_context', $userContext->source);
        self::assertTrue($userContext->modifiable);
        self::assertFalse($userContext->mandatory);
        self::assertNull($userContext->mode);

        $contextStore = $this->app->make(MutableNotificationContextPreferenceStore::class);
        $contextStore->set(
            $context,
            'event.contextual',
            'mail',
            false,
            NotificationContextPreferenceMode::ENFORCED,
        );

        $policy = $user->notificationPreferences()->inspectPreferences($context)['event.contextual']['mail'];

        self::assertFalse($policy->enabled);
        self::assertSame(NotificationDecisionReason::CONTEXT_POLICY, $policy->reason);
        self::assertSame('context_policy', $policy->source);
        self::assertFalse($policy->isModifiable());
        self::assertFalse($policy->modifiable);
        self::assertSame(NotificationContextPreferenceMode::ENFORCED, $policy->mode);
    }

    public function test_context_types_outside_a_definition_are_rejected(): void
    {
        $user = TestUser::query()->create();
        $notification = new TestContextualNotification();
        $context = new NotificationContext('project', 7);

        self::assertFalse($user->canReceiveNotification($notification, 'mail', $context));
    }

    public function test_context_authorizer_protects_preference_access(): void
    {
        $this->app->instance(NotificationContextAuthorizer::class, new DenyingFeatureContextAuthorizer());
        $user = TestUser::query()->create();

        $this->expectException(LogicException::class);
        $user->notificationPreferences()->effective(
            'event.booking_created',
            'mail',
            new NotificationContext('organization', 7),
        );
    }

    public function test_laravel_event_listener_applies_the_same_decision_to_each_channel(): void
    {
        $user = TestUser::query()->create();
        $user->disableNotification('event.booking_created', 'mail');
        $notification = new TestConfiguredNotification();

        $mailResult = $this->app['events']->dispatch(
            new NotificationSending($user, $notification, 'mail'),
            [],
            true,
        );
        $databaseResult = $this->app['events']->dispatch(
            new NotificationSending($user, $notification, 'database'),
            [],
            true,
        );

        self::assertFalse($mailResult);
        self::assertTrue($databaseResult);
    }

    public function test_queueable_notification_context_survives_serialization(): void
    {
        $notification = new TestQueueableNotification();
        $restored = unserialize(serialize($notification));

        self::assertInstanceOf(TestQueueableNotification::class, $restored);
        self::assertSame(
            'organization:7',
            $restored->notificationContext(new TestUser())->key(),
        );
    }

    public function test_real_laravel_send_now_honors_preferences_before_custom_channel_delivery(): void
    {
        $user = TestUser::query()->create();
        $user->enableNotification('test.sendable', 'test');

        NotificationFacade::sendNow($user, new TestSendableNotification());

        self::assertSame(1, TestChannel::$sent);

        $user->disableNotification('test.sendable', 'test');
        NotificationFacade::sendNow($user, new TestSendableNotification());

        self::assertSame(1, TestChannel::$sent);
    }

    public function test_real_queueable_send_uses_the_same_gate(): void
    {
        $user = TestUser::query()->create();
        $user->enableNotification('test.sendable', 'test');

        NotificationFacade::send($user, new TestQueueableSendableNotification());

        self::assertSame(1, TestChannel::$sent);
    }

    public function test_real_multi_recipient_multi_channel_send_is_gated_per_recipient(): void
    {
        $enabled = TestUser::query()->create();
        $disabled = TestUser::query()->create();
        $enabled->enableNotification('test.multi', 'test');
        $enabled->enableNotification('test.multi', 'test_secondary');
        $disabled->disableNotification('test.multi', 'test');
        $disabled->disableNotification('test.multi', 'test_secondary');

        NotificationFacade::sendNow([$enabled, $disabled], new TestMultiChannelNotification());

        self::assertSame(2, TestChannel::$sent);
    }

    public function test_real_database_channel_delivery_is_gated(): void
    {
        $user = TestUser::query()->create();
        $user->enableNotification('test.database', 'database');

        NotificationFacade::sendNow($user, new TestDatabaseNotification());

        self::assertSame(1, DatabaseNotification::query()->count());
    }

    public function test_real_broadcast_channel_delivery_is_gated(): void
    {
        $user = TestUser::query()->create();
        $user->enableNotification('test.broadcast', 'broadcast');
        $received = 0;
        $this->app['events']->listen(
            BroadcastNotificationCreated::class,
            static function () use (&$received): void {
                $received++;
            },
        );

        NotificationFacade::sendNow($user, new TestBroadcastNotification());

        self::assertSame(1, $received);
    }
}

final class TestConfiguredNotification extends Notification
{
}

final class TestContextualNotification extends Notification
{
    public function notificationContext(object $notifiable): NotificationContext
    {
        return new NotificationContext('organization', 7);
    }
}

final class TestQueueableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function notificationContext(object $notifiable): NotificationContext
    {
        return new NotificationContext('organization', 7);
    }
}

class TestSendableNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['test'];
    }
}

final class TestQueueableSendableNotification extends TestSendableNotification implements ShouldQueue
{
    use Queueable;
}

final class TestMultiChannelNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['test', 'test_secondary'];
    }
}

final class TestDatabaseNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => 'stored'];
    }
}

final class TestBroadcastNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    public function toBroadcast(object $notifiable): array
    {
        return ['message' => 'broadcast'];
    }
}

final class TestChannel
{
    public static int $sent = 0;

    public function send(object $notifiable, Notification $notification): void
    {
        self::$sent++;
    }
}

final class TestDefinitionProvider implements NotificationDefinitionProvider
{
    public function register(NotificationDefinitionRegistry $registry): void
    {
        $registry->register(new NotificationDefinition('digest.weekly', ['mail'], optIn: true));
    }
}

final class DenyingFeatureContextAuthorizer implements NotificationContextAuthorizer
{
    public function authorize(object $notifiable, NotificationContext $context): bool
    {
        return false;
    }
}

final class TestUser extends Model
{
    use HasNotificationPreferences, Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}
