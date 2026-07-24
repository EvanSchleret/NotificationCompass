<?php

declare(strict_types=1);

namespace NotificationCompass\Listeners;

use Illuminate\Notifications\Events\NotificationSending;
use NotificationCompass\Resolution\NotificationGate;

final readonly class NotificationSendingListener
{
    public function __construct(private NotificationGate $gate)
    {
    }

    public function handle(NotificationSending $event): bool
    {
        return $this->gate->allows(
            $event->notifiable,
            $event->notification,
            $event->channel,
        );
    }
}
