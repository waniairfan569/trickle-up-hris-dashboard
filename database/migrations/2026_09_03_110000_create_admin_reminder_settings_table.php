<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace configuration for the daily admin reminders (WFH tomorrow /
 * late today). Super-admins toggle each and set the time it fires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->boolean('wfh_enabled')->default(false);
            $table->time('wfh_send_time')->default('08:00:00');
            $table->date('wfh_last_sent_on')->nullable();

            $table->boolean('late_enabled')->default(false);
            $table->time('late_send_time')->default('10:00:00');
            $table->date('late_last_sent_on')->nullable();

            $table->string('timezone')->default('Europe/London');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_reminder_settings');
    }
};
