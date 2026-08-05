<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\ValueObjects\NotificationContext;

interface NotificationContextPolicyAuthorizer
{
    public function authorize(object $administrator, NotificationContext $context): bool;
}
