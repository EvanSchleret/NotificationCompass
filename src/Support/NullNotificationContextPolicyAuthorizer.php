<?php

declare(strict_types=1);

namespace NotificationCompass\Support;

use NotificationCompass\Contracts\NotificationContextPolicyAuthorizer;
use NotificationCompass\ValueObjects\NotificationContext;

final class NullNotificationContextPolicyAuthorizer implements NotificationContextPolicyAuthorizer
{
    public function authorize(object $administrator, NotificationContext $context): bool
    {
        return true;
    }
}
