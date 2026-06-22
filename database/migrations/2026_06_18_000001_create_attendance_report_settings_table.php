<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_report_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->time('send_time')->default('22:00:00');
            $table->json('working_days')->nullable();
            $table->boolean('send_to_super_admin')->default(true);
            $table->boolean('send_to_hr_admin')->default(true);
            $table->boolean('send_to_managers')->default(true);
            $table->json('additional_emails')->nullable();
            $table->boolean('include_late')->default(true);
            $table->boolean('include_absent')->default(true);
            $table->boolean('include_early_departure')->default(true);
            $table->boolean('include_overtime')->default(true);
            $table->boolean('include_on_leave')->default(true);
            $table->boolean('include_full_table')->default(true);
            $table->boolean('attach_excel')->default(false);
            $table->integer('late_threshold_minutes')->default(15);
            $table->string('report_subject_template')->default('Daily Attendance Report — {{date}}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_report_settings');
    }
};
