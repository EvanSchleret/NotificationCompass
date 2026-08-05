<?php

declare(strict_types=1);

namespace NotificationCompass\Resolution;

enum UnknownNotificationBehavior: string
{
    case ALLOW = 'allow';
    case DENY = 'deny';
    case THROW_EXCEPTION = 'throw';
}
