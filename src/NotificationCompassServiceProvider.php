<?php

declare(strict_types=1);

namespace NotificationCompass;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\ServiceProvider;
use NotificationCompass\Contracts\NotificationContextAuthorizer;
use NotificationCompass\Contracts\NotificationContextPolicyAuthorizer;
use NotificationCompass\Contracts\NotificationContextPreferenceStore;
use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\Contracts\NotificationDefinitionProvider;
use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Contracts\NotificationPreferenceCache;
use NotificationCompass\Contracts\MutableNotificationPreferenceStore;
use NotificationCompass\Contracts\MutableNotificationContextPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Listeners\NotificationSendingListener;
use NotificationCompass\Managers\NotificationContextPreferenceManager;
use NotificationCompass\Resolution\PreferenceResolver;
use NotificationCompass\Resolution\NotificationGate;
use NotificationCompass\Stores\EloquentNotificationPreferenceStore;
use NotificationCompass\Stores\EloquentNotificationContextPreferenceStore;
use NotificationCompass\Support\ConventionNotificationContextResolver;
use NotificationCompass\Support\NullNotificationContextAuthorizer;
use NotificationCompass\Support\NullNotificationContextPolicyAuthorizer;
use NotificationCompass\Support\NullNotificationPreferenceCache;
use NotificationCompass\Support\LaravelNotificationPreferenceCache;
use NotificationCompass\Support\StrictNotificationContextAuthorizer;
use NotificationCompass\Support\StrictNotificationContextPolicyAuthorizer;

final class NotificationCompassServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/notificationcompass.php', 'notificationcompass');

        $this->app->singleton(NotificationDefinitionRegistry::class);
        $this->app->singleton(NotificationContextAuthorizer::class, function (Application $app): NotificationContextAuthorizer {
            return (bool) $app['config']->get('notificationcompass.authorization.strict', false)
                ? new StrictNotificationContextAuthorizer()
                : new NullNotificationContextAuthorizer();
        });
        $this->app->singleton(NotificationContextPolicyAuthorizer::class, function (Application $app): NotificationContextPolicyAuthorizer {
            return (bool) $app['config']->get('notificationcompass.authorization.strict', false)
                ? new StrictNotificationContextPolicyAuthorizer()
                : new NullNotificationContextPolicyAuthorizer();
        });
        $this->app->singleton(NotificationContextResolver::class, ConventionNotificationContextResolver::class);
        $this->app->singleton(
            MutableNotificationContextPreferenceStore::class,
            EloquentNotificationContextPreferenceStore::class,
        );
        $this->app->alias(
            MutableNotificationContextPreferenceStore::class,
            NotificationContextPreferenceStore::class,
        );
        $this->app->singleton(NotificationContextPreferenceManager::class);
        $this->app->singleton(MutableNotificationPreferenceStore::class, EloquentNotificationPreferenceStore::class);
        $this->app->alias(MutableNotificationPreferenceStore::class, NotificationPreferenceStore::class);
        $this->app->singleton(NotificationPreferenceCache::class, function (Application $app): NotificationPreferenceCache {
            if (! (bool) $app['config']->get('notificationcompass.cache.enabled', true)) {
                return new NullNotificationPreferenceCache();
            }

            $store = $app['config']->get('notificationcompass.cache.store');

            return new LaravelNotificationPreferenceCache(
                $app->make(CacheFactory::class)->store(is_string($store) ? $store : null),
                (int) $app['config']->get('notificationcompass.cache.ttl', 300),
                (string) $app['config']->get('notificationcompass.cache.prefix', 'notificationcompass:preferences'),
            );
        });
        $this->app->singleton(PreferenceResolver::class, function (Application $app): PreferenceResolver {
            return new PreferenceResolver(
                $app->make(NotificationDefinitionRegistry::class),
                $app['config']->get('notificationcompass.channels', []),
                (bool) $app['config']->get('notificationcompass.default', false),
                $app->make(NotificationContextPreferenceStore::class),
                $app->make(NotificationPreferenceCache::class),
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
