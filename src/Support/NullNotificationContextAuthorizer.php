<?php

declare(strict_types=1);

namespace NotificationCompass\Support;

use NotificationCompass\Contracts\NotificationContextAuthorizer;
use NotificationCompass\ValueObjects\NotificationContext;

final class NullNotificationContextAuthorizer implements NotificationContextAuthorizer
{
    public function authorize(object $notifiable, NotificationContext $context): bool
    {
        return true;
    }
}
