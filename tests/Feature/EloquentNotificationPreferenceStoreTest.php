<?php

declare(strict_types=1);

namespace NotificationCompass\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NotificationCompass\Concerns\HasNotificationPreferences;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Contracts\NotificationDefinitionProvider;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\NotificationCompassServiceProvider;
use NotificationCompass\Stores\EloquentNotificationPreferenceStore;
use NotificationCompass\ValueObjects\NotificationContext;
use Orchestra\Testbench\TestCase;
use InvalidArgumentException;
use LogicException;

final class EloquentNotificationPreferenceStoreTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NotificationCompassServiceProvider::class];
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
                'channels' => ['mail'],
                'notification_class' => TestConfiguredNotification::class,
            ],
            'security.alert' => [
                'channels' => ['mail', 'database'],
                'mandatory_channels' => ['mail'],
                'channel_options' => [
                    'database' => ['hidden' => true, 'default' => true],
                ],
            ],
            'event.contextual' => [
                'channels' => ['mail'],
                'notification_class' => TestContextualNotification::class,
                'supported_contexts' => ['organization'],
            ],
        ]);
        $app['config']->set('notificationcompass.definition_providers', [TestDefinitionProvider::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
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

    public function test_definitions_are_loaded_from_configuration(): void
    {
        $definition = $this->app->make(NotificationDefinitionRegistry::class)
            ->get('event.booking_created');

        self::assertSame(['mail'], $definition->channels);
        self::assertSame(TestConfiguredNotification::class, $definition->notificationClass);
        self::assertTrue($this->app->make(NotificationDefinitionRegistry::class)->has('digest.weekly'));
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

    public function test_context_types_outside_a_definition_are_rejected(): void
    {
        $user = TestUser::query()->create();
        $notification = new TestContextualNotification();
        $context = new NotificationContext('project', 7);

        self::assertFalse($user->canReceiveNotification($notification, 'mail', $context));
    }
}

final class TestConfiguredNotification
{
}

final class TestContextualNotification
{
    public function notificationContext(object $notifiable): NotificationContext
    {
        return new NotificationContext('organization', 7);
    }
}

final class TestDefinitionProvider implements NotificationDefinitionProvider
{
    public function register(NotificationDefinitionRegistry $registry): void
    {
        $registry->register(new NotificationDefinition('digest.weekly', ['mail'], optIn: true));
    }
}

final class TestUser extends Model
{
    use HasNotificationPreferences;

    protected $table = 'users';

    protected $guarded = [];
}
