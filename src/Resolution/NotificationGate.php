<?php

declare(strict_types=1);

namespace NotificationCompass\Resolution;

use Illuminate\Contracts\Events\Dispatcher;
use LogicException;
use NotificationCompass\Contracts\NotificationContextResolver;
use NotificationCompass\Contracts\NotificationContextAuthorizer;
use NotificationCompass\Contracts\NotificationDeliveryDecisionCustomizer;
use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Events\NotificationDeliveryDecided;
use NotificationCompass\ValueObjects\NotificationContext;

final readonly class NotificationGate
{
    public function __construct(
        private NotificationDefinitionRegistry $definitions,
        private NotificationContextResolver $contexts,
        private NotificationPreferenceStore $preferences,
        private PreferenceResolver $resolver,
        private ?NotificationContextAuthorizer $authorizer = null,
        private UnknownNotificationBehavior $unknownNotificationBehavior = UnknownNotificationBehavior::ALLOW,
        private ?NotificationDeliveryDecisionCustomizer $customizer = null,
        private ?Dispatcher $events = null,
    ) {
    }

    public function decision(
        object $notifiable,
        object $notification,
        string $channel,
        ?NotificationContext $context = null,
    ): ?ResolvedPreference {
        $definition = $this->definitions->forNotification($notification);
        if ($definition === null) {
            if ($this->unknownNotificationBehavior === UnknownNotificationBehavior::THROW_EXCEPTION) {
                throw new LogicException(
                    'Notification [' . get_class($notification) . '] is not registered in NotificationDefinitionRegistry.',
                );
            }

            $preference = $this->dispatchDecision(
                $notifiable,
                $notification,
                $channel,
                $context,
                null,
                new ResolvedPreference(
                    $this->unknownNotificationBehavior === UnknownNotificationBehavior::ALLOW,
                    NotificationDecisionReason::UNKNOWN_NOTIFICATION,
                ),
            );

            return $this->unknownNotificationBehavior === UnknownNotificationBehavior::ALLOW
                ? null
                : $preference;
        }

        if (! $definition->hasChannel($channel)) {
            return $this->dispatchDecision(
                $notifiable,
                $notification,
                $channel,
                $context,
                $definition,
                new ResolvedPreference(false, NotificationDecisionReason::CHANNEL_UNDECLARED),
            );
        }

        $resolvedContext = $context ?? $this->contexts->resolve($notification, $notifiable);
        if (! $definition->supportsContext($resolvedContext)) {
            return $this->dispatchDecision(
                $notifiable,
                $notification,
                $channel,
                $resolvedContext,
                $definition,
                new ResolvedPreference(false, NotificationDecisionReason::CONTEXT_UNSUPPORTED),
            );
        }

        if ($resolvedContext !== null && $this->authorizer !== null
            && ! $this->authorizer->authorize($notifiable, $resolvedContext)) {
            return $this->dispatchDecision(
                $notifiable,
                $notification,
                $channel,
                $resolvedContext,
                $definition,
                new ResolvedPreference(false, NotificationDecisionReason::CONTEXT_UNAUTHORIZED),
            );
        }

        return $this->dispatchDecision(
            $notifiable,
            $notification,
            $channel,
            $resolvedContext,
            $definition,
            $this->resolver->resolve(
                $notifiable,
                $definition->key,
                $channel,
                $resolvedContext,
                $this->preferences,
            ),
        );
    }

    private function dispatchDecision(
        object $notifiable,
        object $notification,
        string $channel,
        ?NotificationContext $context,
        ?NotificationDefinition $definition,
        ResolvedPreference $preference,
    ): ResolvedPreference {
        $originalEnabled = $preference->enabled;
        $customized = false;

        if ($definition !== null && $this->customizer !== null && $this->canCustomize($preference)) {
            $enabled = $this->customizer->customize(
                $notifiable,
                $notification,
                $channel,
                $context,
                $definition,
                $preference,
            );

            if ($enabled !== null && $enabled !== $preference->enabled) {
                $preference = new ResolvedPreference(
                    $enabled,
                    $preference->reason,
                    $preference->mandatory,
                    $preference->mode,
                );
                $customized = true;
            }
        }

        $this->events?->dispatch(new NotificationDeliveryDecided(
            $notifiable,
            $notification,
            $channel,
            $context,
            $definition,
            $preference,
            $customized,
            $customized ? $originalEnabled : null,
        ));

        return $preference;
    }

    private function canCustomize(ResolvedPreference $preference): bool
    {
        return $preference->isModifiable()
            && ! in_array($preference->reason, [
                NotificationDecisionReason::CHANNEL_UNDECLARED,
                NotificationDecisionReason::CONTEXT_REQUIRED,
                NotificationDecisionReason::CONTEXT_UNSUPPORTED,
                NotificationDecisionReason::CONTEXT_UNAUTHORIZED,
                NotificationDecisionReason::MANDATORY,
            ], true);
    }

    public function allows(
        object $notifiable,
        object $notification,
        string $channel,
        ?NotificationContext $context = null,
    ): bool {
        return $this->decision($notifiable, $notification, $channel, $context)?->enabled ?? true;
    }
}
