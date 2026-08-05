<?php

declare(strict_types=1);

namespace NotificationCompass\Managers;

use InvalidArgumentException;
use LogicException;
use NotificationCompass\Contracts\MutableNotificationContextPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\ValueObjects\NotificationContext;
use NotificationCompass\ValueObjects\NotificationContextPreference;
use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;
use NotificationCompass\ValueObjects\NotificationContextPolicyInspection;

final readonly class NotificationContextPreferenceManager
{
    public function __construct(
        private MutableNotificationContextPreferenceStore $store,
        private NotificationDefinitionRegistry            $definitions,
    )
    {
    }

    public function get(
        NotificationContext $context,
        string              $notificationKey,
        string              $channel,
    ): ?NotificationContextPreference
    {
        $this->assertPolicySupported($context, $notificationKey, $channel);

        return $this->store->get($context, $notificationKey, $channel);
    }

    public function set(
        NotificationContext               $context,
        string                            $notificationKey,
        string                            $channel,
        bool                              $enabled,
        NotificationContextPreferenceMode $mode = NotificationContextPreferenceMode::DEFAULT,
    ): void
    {
        $this->assertPolicySupported($context, $notificationKey, $channel);
        $this->store->set($context, $notificationKey, $channel, $enabled, $mode);
    }

    public function enable(
        NotificationContext               $context,
        string                            $notificationKey,
        string                            $channel,
        NotificationContextPreferenceMode $mode = NotificationContextPreferenceMode::DEFAULT,
    ): void
    {
        $this->set($context, $notificationKey, $channel, true, $mode);
    }

    public function disable(
        NotificationContext               $context,
        string                            $notificationKey,
        string                            $channel,
        NotificationContextPreferenceMode $mode = NotificationContextPreferenceMode::DEFAULT,
    ): void
    {
        $this->set($context, $notificationKey, $channel, false, $mode);
    }

    public function reset(
        NotificationContext $context,
        string              $notificationKey,
        string              $channel,
    ): void
    {
        $this->assertPolicySupported($context, $notificationKey, $channel);
        $this->store->forget($context, $notificationKey, $channel);
    }

    /** @return array<string, array<string, NotificationContextPolicyInspection>> */
    public function inspect(NotificationContext $context): array
    {
        $policies = [];

        foreach ($this->definitions->all() as $definition) {
            if (!$definition->supportsContext($context)) {
                continue;
            }

            foreach ($definition->channels as $channel) {
                $policies[$definition->key][$channel] = new NotificationContextPolicyInspection(
                    context: $context,
                    definition: $definition,
                    channel: $channel,
                    preference: $this->store->get($context, $definition->key, $channel),
                    mandatory: $definition->isMandatoryFor($channel),
                );
            }
        }

        return $policies;
    }

    private function assertPolicySupported(
        NotificationContext $context,
        string              $notificationKey,
        string              $channel,
    ): NotificationDefinition
    {
        $definition = $this->definitions->get($notificationKey);

        if (!$definition->hasChannel($channel)) {
            throw new InvalidArgumentException(
                "Channel [{$channel}] is not available for notification type [{$notificationKey}].",
            );
        }

        if (!$definition->supportsContext($context)) {
            throw new InvalidArgumentException(
                "Notification type [{$notificationKey}] does not support context type [{$context->type}].",
            );
        }

        if ($definition->isMandatoryFor($channel)) {
            throw new LogicException(
                "Channel [{$channel}] cannot have a context policy because it is mandatory for notification type [{$notificationKey}].",
            );
        }

        return $definition;
    }
}
