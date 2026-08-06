<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('notificationcompass.table', 'notificationcompass_preferences'), function (Blueprint $table): void {
            $table->id();
            $table->string('notifiable_type');
            $table->string('notifiable_id');
            $table->string('notification_key');
            $table->string('channel');
            $table->string('context_key')->default('');
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique([
                'notifiable_type',
                'notifiable_id',
                'notification_key',
                'channel',
                'context_key',
            ], 'notificationcompass_preferences_unique');
            $table->index(
                ['notifiable_type', 'notifiable_id'],
                'notificationcompass_preferences_notifiable_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('notificationcompass.table', 'notificationcompass_preferences'));
    }
};
