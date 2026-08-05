<?php

declare(strict_types=1);

namespace NotificationCompass\ValueObjects;

use NotificationCompass\Definitions\NotificationDefinition;

final readonly class NotificationContextPolicyInspection
{
    public readonly NotificationContext $context;
    public readonly NotificationDefinition $definition;
    public readonly string $channel;
    public readonly ?NotificationContextPreference $preference;
    public readonly bool $mandatory;
    public readonly bool $configured;
    public readonly bool $modifiable;
    public readonly ?bool $enabled;
    public readonly ?NotificationContextPreferenceMode $mode;

    public function __construct(
        NotificationContext            $context,
        NotificationDefinition         $definition,
        string                         $channel,
        ?NotificationContextPreference $preference,
        bool                           $mandatory,
    )
    {
        $this->context = $context;
        $this->definition = $definition;
        $this->channel = $channel;
        $this->preference = $preference;
        $this->mandatory = $mandatory;
        $this->configured = $preference !== null;
        $this->modifiable = !$mandatory;
        $this->enabled = $preference?->enabled;
        $this->mode = $preference?->mode;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function isModifiable(): bool
    {
        return $this->modifiable;
    }
}
