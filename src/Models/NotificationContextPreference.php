<?php

declare(strict_types=1);

namespace NotificationCompass\Models;

use Illuminate\Database\Eloquent\Model;
use NotificationCompass\ValueObjects\NotificationContextPreferenceMode;

final class NotificationContextPreference extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'mode' => NotificationContextPreferenceMode::class,
    ];

    public function getTable(): string
    {
        return (string) config(
            'notificationcompass.context_table',
            'notificationcompass_context_preferences',
        );
    }
}
