<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Resolution\ResolvedPreference;
use NotificationCompass\ValueObjects\NotificationContext;

interface NotificationDeliveryDecisionCustomizer
{
    public function customize(
        object $notifiable,
        object $notification,
        string $channel,
        ?NotificationContext $context,
        NotificationDefinition $definition,
        ResolvedPreference $preference,
    ): ?bool;
}
