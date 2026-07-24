<?php

declare(strict_types=1);

namespace NotificationCompass\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NotificationCompass\Concerns\HasNotificationPreferences;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\NotificationCompassServiceProvider;
use NotificationCompass\Stores\EloquentNotificationPreferenceStore;
use NotificationCompass\ValueObjects\NotificationContext;
use Orchestra\Testbench\TestCase;

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
            ],
        ]);
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
    }

    public function test_notification_context_can_round_trip_through_arrays_and_json(): void
    {
        $context = new NotificationContext('organization', 42, ['name' => 'Acme']);
        $restored = NotificationContext::fromArray($context->toArray());

        self::assertSame('organization:42', $restored->key());
        self::assertSame($context->toArray(), json_decode((string) json_encode($context), true));
    }
}

final class TestConfiguredNotification
{
}

final class TestUser extends Model
{
    use HasNotificationPreferences;

    protected $table = 'users';

    protected $guarded = [];
}
