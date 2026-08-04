<?php

declare(strict_types=1);

namespace NotificationCompass\Models;

use Illuminate\Database\Eloquent\Model;

final class NotificationPreference extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function getTable(): string
    {
        return (string) config('notificationcompass.table', 'notificationcompass_preferences');
    }
}
