<?php

declare(strict_types=1);

namespace NotificationCompass\Resolution;

enum NotificationDecisionReason: string
{
    case UNKNOWN_NOTIFICATION = 'unknown_notification';
    case MANDATORY = 'mandatory';
    case CHANNEL_UNDECLARED = 'channel_undeclared';
    case CONTEXT_REQUIRED = 'context_required';
    case CONTEXT_UNSUPPORTED = 'context_unsupported';
    case CONTEXT_UNAUTHORIZED = 'context_unauthorized';
    case USER_CONTEXT = 'user_context';
    case USER_GLOBAL = 'user_global';
    case CONTEXT_POLICY = 'context_policy';
    case TYPE_CONTEXT_DEFAULT = 'type_context_default';
    case TYPE_DEFAULT = 'type_default';
    case CHANNEL_DEFINITION_DEFAULT = 'channel_definition_default';
    case OPT_IN_DEFAULT = 'opt_in_default';
    case CHANNEL_DEFAULT = 'channel_default';
    case PACKAGE_DEFAULT = 'package_default';

    public function isDefault(): bool
    {
        return match ($this) {
            self::TYPE_CONTEXT_DEFAULT,
            self::TYPE_DEFAULT,
            self::CHANNEL_DEFINITION_DEFAULT,
            self::OPT_IN_DEFAULT,
            self::CHANNEL_DEFAULT,
            self::PACKAGE_DEFAULT => true,
            default => false,
        };
    }
}
