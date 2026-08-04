<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('notificationcompass.context_table', 'notificationcompass_context_preferences'), function (Blueprint $table): void {
            $table->id();
            $table->string('context_key');
            $table->string('notification_key');
            $table->string('channel');
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique([
                'context_key',
                'notification_key',
                'channel',
            ], 'notificationcompass_context_preferences_unique');
            $table->index('context_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('notificationcompass.context_table', 'notificationcompass_context_preferences'));
    }
};
