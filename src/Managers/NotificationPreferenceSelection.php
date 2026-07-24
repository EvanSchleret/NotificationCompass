<?php

declare(strict_types=1);

namespace NotificationCompass\Managers;

use NotificationCompass\Resolution\ResolvedPreference;
use NotificationCompass\ValueObjects\NotificationContext;

final readonly class NotificationPreferenceSelection
{
    public function __construct(
        private NotificationPreferenceManager $manager,
        private string $notificationKey,
        private ?NotificationContext $context,
    ) {
    }

    public function enable(string $channel): void
    {
        $this->manager->enable($this->notificationKey, $channel, $this->context);
    }

    public function disable(string $channel): void
    {
        $this->manager->disable($this->notificationKey, $channel, $this->context);
    }

    public function reset(string $channel): void
    {
        $this->manager->reset($this->notificationKey, $channel, $this->context);
    }

    public function explicit(string $channel): ?bool
    {
        return $this->manager->explicit($this->notificationKey, $channel, $this->context);
    }

    public function effective(string $channel): ResolvedPreference
    {
        return $this->manager->effective($this->notificationKey, $channel, $this->context);
    }
}
