<?php

declare(strict_types=1);

namespace NotificationCompass\Resolution;

use NotificationCompass\Contracts\NotificationPreferenceStore;
use NotificationCompass\Contracts\NotificationPreferenceCache;
use NotificationCompass\Contracts\NotificationContextPreferenceStore;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Definitions\NotificationDefinitionRegistry;
use NotificationCompass\ValueObjects\NotificationContext;
use NotificationCompass\ValueObjects\NotificationContextPreference;
use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;

final readonly class PreferenceResolver
{
    /** @param array<string, bool> $channelDefaults */
    public function __construct(
        private NotificationDefinitionRegistry $definitions,
        private array $channelDefaults,
        private bool $packageDefault,
        private ?NotificationContextPreferenceStore $contextPreferences = null,
        private ?NotificationPreferenceCache $cache = null,
    ) {
    }

    public function resolve(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
        NotificationPreferenceStore $preferences,
    ): ResolvedPreference {
        $cached = $this->cache?->get($notifiable, $notificationKey, $channel, $context);
        if ($cached !== null) {
            return $cached;
        }

        $preference = $this->resolveWithoutCache(
            $notifiable,
            $notificationKey,
            $channel,
            $context,
            $preferences,
        );
        $this->cache?->put($notifiable, $notificationKey, $channel, $context, $preference);

        return $preference;
    }

    private function resolveWithoutCache(
        object $notifiable,
        string $notificationKey,
        string $channel,
        ?NotificationContext $context,
        NotificationPreferenceStore $preferences,
    ): ResolvedPreference {
        $definition = $this->definitions->get($notificationKey);

        if ($definition->requiresContext && $context === null) {
            return new ResolvedPreference(false, NotificationDecisionReason::CONTEXT_REQUIRED);
        }

        if ($definition->isMandatoryFor($channel)) {
            return new ResolvedPreference(true, NotificationDecisionReason::MANDATORY, true);
        }

        $contextPreference = $this->contextPreference($context, $notificationKey, $channel);
        if ($contextPreference?->mode === NotificationContextPreferenceMode::ENFORCED) {
            return $this->resolvedContextPreference($contextPreference);
        }

        $userContextPreference = $preferences->get($notifiable, $notificationKey, $channel, $context);
        if ($userContextPreference !== null && $context !== null) {
            return new ResolvedPreference($userContextPreference, NotificationDecisionReason::USER_CONTEXT);
        }

        $globalPreference = $preferences->get($notifiable, $notificationKey, $channel, null);
        if ($globalPreference !== null) {
            return new ResolvedPreference($globalPreference, NotificationDecisionReason::USER_GLOBAL);
        }

        if ($contextPreference !== null) {
            return $this->resolvedContextPreference($contextPreference);
        }

        $contextDefault = $this->contextDefault($definition, $context, $channel);
        if ($contextDefault !== null) {
            return new ResolvedPreference($contextDefault, NotificationDecisionReason::TYPE_CONTEXT_DEFAULT);
        }

        if (array_key_exists($channel, $definition->defaults)) {
            return new ResolvedPreference($definition->defaults[$channel], NotificationDecisionReason::TYPE_DEFAULT);
        }

        $channelDefault = $definition->channelDefault($channel);
        if ($channelDefault !== null) {
            return new ResolvedPreference(
                $channelDefault,
                NotificationDecisionReason::CHANNEL_DEFINITION_DEFAULT,
            );
        }

        if ($definition->isOptInFor($channel)) {
            return new ResolvedPreference(false, NotificationDecisionReason::OPT_IN_DEFAULT);
        }

        if (array_key_exists($channel, $this->channelDefaults)) {
            return new ResolvedPreference($this->channelDefaults[$channel], NotificationDecisionReason::CHANNEL_DEFAULT);
        }

        return new ResolvedPreference($this->packageDefault, NotificationDecisionReason::PACKAGE_DEFAULT);
    }

    private function contextPreference(
        ?NotificationContext $context,
        string $notificationKey,
        string $channel,
    ): ?NotificationContextPreference {
        if ($context === null || $this->contextPreferences === null) {
            return null;
        }

        return $this->contextPreferences->get($context, $notificationKey, $channel);
    }

    private function resolvedContextPreference(NotificationContextPreference $preference): ResolvedPreference
    {
        return new ResolvedPreference(
            $preference->enabled,
            NotificationDecisionReason::CONTEXT_POLICY,
            false,
            $preference->mode,
        );
    }

    private function contextDefault(
        NotificationDefinition $definition,
        ?NotificationContext $context,
        string $channel,
    ): ?bool {
        if ($context === null || ! isset($definition->contextDefaults[$context->key()])) {
            return null;
        }

        return $definition->contextDefaults[$context->key()][$channel] ?? null;
    }
}
