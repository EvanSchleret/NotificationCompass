<?php

declare(strict_types=1);

namespace NotificationCompass\Events;

enum NotificationPreferenceChangeType: string
{
    case CREATED = 'created';
    case MODIFIED = 'modified';
    case RESET = 'reset';
    case DELETED = 'deleted';
}
