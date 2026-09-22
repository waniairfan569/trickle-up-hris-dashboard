<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring reminder for the finance/approved-overtime report: an admin picks a
 * cadence (monthly on day N, or weekly) + time + recipients, and gets a
 * dashboard notification + email to generate the report. Stored alongside the
 * existing WFH/late reminders on the per-tenant AdminReminderSetting singleton.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_reminder_settings', function (Blueprint $table) {
            $table->boolean('overtime_enabled')->default(false)->after('late_last_sent_on');
            $table->string('overtime_frequency', 12)->default('monthly')->after('overtime_enabled'); // monthly | weekly
            $table->unsignedTinyInteger('overtime_day')->default(1)->after('overtime_frequency');     // day of month (monthly)
            $table->unsignedTinyInteger('overtime_weekday')->nullable()->after('overtime_day');        // ISO weekday 1-7 (weekly)
            $table->time('overtime_send_time')->default('09:00:00')->after('overtime_weekday');
            $table->json('overtime_recipients')->nullable()->after('overtime_send_time');              // user ids; empty = all admins
            $table->string('overtime_last_sent_key', 16)->nullable()->after('overtime_recipients');    // period guard (YYYY-MM or o-Www)
        });
    }

    public function down(): void
    {
        Schema::table('admin_reminder_settings', function (Blueprint $table) {
            $table->dropColumn([
                'overtime_enabled', 'overtime_frequency', 'overtime_day', 'overtime_weekday',
                'overtime_send_time', 'overtime_recipients', 'overtime_last_sent_key',
            ]);
        });
    }
};
