<?php

declare(strict_types=1);

namespace NotificationCompass;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\ServiceProvider;
use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Contracts\MutableNotificationPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Listeners\NotificationSendingListener;
use NotificationCompass\Resolution\PreferenceResolver;
use NotificationCompass\Resolution\NotificationGate;
use NotificationCompass\Stores\EloquentNotificationPreferenceStore;
use NotificationCompass\Support\NullNotificationContextResolver;

final class NotificationCompassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/notificationcompass.php', 'notificationcompass');

        $this->app->singleton(NotificationDefinitionRegistry::class);
        $this->app->singleton(NotificationContextResolver::class, NullNotificationContextResolver::class);
        $this->app->singleton(MutableNotificationPreferenceStore::class, EloquentNotificationPreferenceStore::class);
        $this->app->alias(MutableNotificationPreferenceStore::class, NotificationPreferenceStore::class);
        $this->app->singleton(PreferenceResolver::class, function (Application $app): PreferenceResolver {
            return new PreferenceResolver(
                $app->make(NotificationDefinitionRegistry::class),
                $app['config']->get('notificationcompass.channels', []),
                (bool) $app['config']->get('notificationcompass.default', false),
            );
        });
        $this->app->singleton(NotificationGate::class);
    }

    private function registerConfiguredDefinitions(): void
    {
        $registry = $this->app->make(NotificationDefinitionRegistry::class);

        foreach ((array) config('notificationcompass.definitions', []) as $key => $attributes) {
            if (is_array($attributes)) {
                $registry->register(NotificationDefinition::fromConfig((string) $key, $attributes));
            }
        }
    }

    public function boot(): void
    {
        $this->registerConfiguredDefinitions();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->app['events']->listen(NotificationSending::class, NotificationSendingListener::class);

        $this->publishes([
            __DIR__ . '/../config/notificationcompass.php' => config_path('notificationcompass.php'),
        ], 'notificationcompass-config');
    }
}
