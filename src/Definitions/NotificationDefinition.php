<?php

declare(strict_types=1);

namespace NotificationCompass\Definitions;

use InvalidArgumentException;
use NotificationCompass\ValueObjects\NotificationChannelMetadata;
use NotificationCompass\ValueObjects\NotificationContext;
use NotificationCompass\ValueObjects\NotificationDefinitionMetadata;

final readonly class NotificationDefinition
{
    public static function fromConfig(string $key, array $attributes): self
    {
        $metadata = NotificationDefinitionMetadata::fromConfig($attributes);
        $channelOptions = $attributes['channel_options'] ?? [];
        $channelMetadata = [];

        foreach ((array) ($attributes['channel_metadata'] ?? []) as $channel => $channelAttributes) {
            if (is_array($channelAttributes)) {
                $channelMetadata[$channel] = NotificationChannelMetadata::fromConfig($channelAttributes);
            }
        }

        return new self(
            key: $key,
            channels: $attributes['channels'] ?? [],
            defaults: $attributes['defaults'] ?? [],
            mandatory: (bool) ($attributes['mandatory'] ?? false),
            mandatoryChannels: $attributes['mandatory_channels'] ?? [],
            optIn: (bool) ($attributes['opt_in'] ?? false),
            contextDefaults: $attributes['context_defaults'] ?? [],
            notificationClass: $attributes['notification_class'] ?? null,
            channelOptions: $channelOptions,
            supportedContexts: $attributes['supported_contexts'] ?? [],
            configurable: (bool) ($attributes['configurable'] ?? true),
            metadata: $metadata,
            channelMetadata: $channelMetadata,
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
        public array $channelOptions = [],
        public array $supportedContexts = [],
        public bool $configurable = true,
        ?NotificationDefinitionMetadata $metadata = null,
        public array $channelMetadata = [],
    ) {
        $this->metadata = $metadata ?? new NotificationDefinitionMetadata();

        if ($this->key === '') {
            throw new InvalidArgumentException('A notification definition key cannot be empty.');
        }
    }

    public readonly NotificationDefinitionMetadata $metadata;

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
        return ! $this->channelMetadata($channel)->visible;
    }

    public function channelMetadata(string $channel): NotificationChannelMetadata
    {
        $metadata = $this->channelMetadata[$channel] ?? null;

        return $metadata instanceof NotificationChannelMetadata
            ? $metadata
            : NotificationChannelMetadata::fromConfig(is_array($metadata) ? $metadata : []);
    }

    public function supportsContext(?NotificationContext $context): bool
    {
        return $context === null
            || $this->supportedContexts === []
            || in_array($context->type, $this->supportedContexts, true);
    }
}
