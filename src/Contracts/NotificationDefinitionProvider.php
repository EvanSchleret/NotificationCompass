<?php

declare(strict_types=1);

namespace NotificationCompass\Contracts;

use NotificationCompass\Definitions\NotificationDefinitionRegistry;

interface NotificationDefinitionProvider
{
    public function register(NotificationDefinitionRegistry $registry): void;
}
