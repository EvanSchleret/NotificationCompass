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
    }

    public function test_definitions_are_loaded_from_configuration(): void
    {
        (new NotificationCompassServiceProvider($this->app))->register();

        $definition = $this->app->make(NotificationDefinitionRegistry::class)
            ->get('event.booking_created');

        self::assertSame(['mail'], $definition->channels);
        self::assertSame(TestConfiguredNotification::class, $definition->notificationClass);
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
