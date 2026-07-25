<?php

declare(strict_types=1);

namespace NotificationCompass\Definitions;

use InvalidArgumentException;

final readonly class NotificationDefinition
{
    public static function fromConfig(string $key, array $attributes): self
    {
        return new self(
            key: $key,
            channels: $attributes['channels'] ?? [],
            defaults: $attributes['defaults'] ?? [],
            mandatory: (bool) ($attributes['mandatory'] ?? false),
            mandatoryChannels: $attributes['mandatory_channels'] ?? [],
            optIn: (bool) ($attributes['opt_in'] ?? false),
            contextDefaults: $attributes['context_defaults'] ?? [],
            notificationClass: $attributes['notification_class'] ?? null,
            name: $attributes['name'] ?? null,
            description: $attributes['description'] ?? null,
            category: $attributes['category'] ?? null,
            channelOptions: $attributes['channel_options'] ?? [],
            supportedContexts: $attributes['supported_contexts'] ?? [],
            configurable: (bool) ($attributes['configurable'] ?? true),
        );
    }

    public function __construct(
        public string $key,
        public array $channels,
        public array $defaults = [],
        public bool $mandatory = false,
        public array $mandatoryChannels = [],
        public bool $optIn = false,
        public array $contextDefaults = [],
        public ?string $notificationClass = null,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $category = null,
        public array $channelOptions = [],
        public array $supportedContexts = [],
        public bool $configurable = true,
    ) {
        if ($this->key === '') {
            throw new InvalidArgumentException('A notification definition key cannot be empty.');
        }
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    public function isMandatoryFor(string $channel): bool
    {
        return $this->mandatory
            || in_array($channel, $this->mandatoryChannels, true)
            || (bool) ($this->channelOptions[$channel]['mandatory'] ?? false);
    }

    public function isOptInFor(string $channel): bool
    {
        return $this->optIn || (bool) ($this->channelOptions[$channel]['opt_in'] ?? false);
    }

    public function isModifiableFor(string $channel): bool
    {
        return $this->configurable
            && (bool) ($this->channelOptions[$channel]['configurable'] ?? true)
            && ! $this->isMandatoryFor($channel);

    }

    public function channelDefault(string $channel): ?bool
    {
        $value = $this->channelOptions[$channel]['default'] ?? null;

        return is_bool($value) ? $value : null;
    }

    public function isHidden(string $channel): bool
    {
        return (bool) ($this->channelOptions[$channel]['hidden'] ?? false);
    }
}
