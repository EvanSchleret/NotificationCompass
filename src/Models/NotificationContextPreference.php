<?php

declare(strict_types=1);

namespace NotificationCompass\Models;

use Illuminate\Database\Eloquent\Model;

final class NotificationContextPreference extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function getTable(): string
    {
        return (string) config(
            'notificationcompass.context_table',
            'notification_context_preferences',
        );
    }
}
