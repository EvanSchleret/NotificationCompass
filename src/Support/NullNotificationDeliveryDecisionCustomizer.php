<?php

declare(strict_types=1);

namespace NotificationCompass\Support;

use NotificationCompass\Contracts\NotificationDeliveryDecisionCustomizer;
use NotificationCompass\Definitions\NotificationDefinition;
use NotificationCompass\Resolution\ResolvedPreference;
use NotificationCompass\ValueObjects\NotificationContext;

final class NullNotificationDeliveryDecisionCustomizer implements NotificationDeliveryDecisionCustomizer
{
    public function customize(
        object $notifiable,
        object $notification,
        string $channel,
        ?NotificationContext $context,
        NotificationDefinition $definition,
        ResolvedPreference $preference,
    ): ?bool {
        return null;
    }
}
