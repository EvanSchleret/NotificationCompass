<?php

declare(strict_types=1);

namespace NotificationCompass\ValueObjects;

enum NotificationContextPreferenceMode: string
{
    case DEFAULT = 'default';
    case ENFORCED = 'enforced';
}
