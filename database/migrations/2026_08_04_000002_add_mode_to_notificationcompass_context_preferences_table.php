<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('notificationcompass.context_table', 'notificationcompass_context_preferences'), function (Blueprint $table): void {
            $table->string('mode')->default('default')->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table(config('notificationcompass.context_table', 'notificationcompass_context_preferences'), function (Blueprint $table): void {
            $table->dropColumn('mode');
        });
    }
};
