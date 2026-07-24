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
        return $this->mandatory || in_array($channel, $this->mandatoryChannels, true);
    }
}
