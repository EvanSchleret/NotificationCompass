<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\ValueObjects\NotificationContext;

interface NotificationContextAuthorizer
{
    public function authorize(object $notifiable, NotificationContext $context): bool;
}
