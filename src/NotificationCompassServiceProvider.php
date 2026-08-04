<?php

declare(strict_types=1);

namespace NotificationCompass;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\ServiceProvider;
use NotificationCompass\Contracts\NotificationContextAuthorizer;
use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\Contracts\NotificationDefinitionProvider;
use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Contracts\MutableNotificationPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Listeners\NotificationSendingListener;
use NotificationCompass\Resolution\PreferenceResolver;
use NotificationCompass\Resolution\NotificationGate;
use NotificationCompass\Stores\EloquentNotificationPreferenceStore;
use NotificationCompass\Support\ConventionNotificationContextResolver;
use NotificationCompass\Support\NullNotificationContextAuthorizer;

final class NotificationCompassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/notificationcompass.php', 'notificationcompass');

        $this->app->singleton(NotificationDefinitionRegistry::class);
        $this->app->singleton(NotificationContextAuthorizer::class, NullNotificationContextAuthorizer::class);
        $this->app->singleton(NotificationContextResolver::class, ConventionNotificationContextResolver::class);
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

        foreach ((array) config('notificationcompass.definition_providers', []) as $providerClass) {
            $provider = $this->app->make($providerClass);
            if (! $provider instanceof NotificationDefinitionProvider) {
                throw new \InvalidArgumentException(
                    "Definition provider [{$providerClass}] must implement NotificationDefinitionProvider.",
                );
            }

            $provider->register($registry);
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
