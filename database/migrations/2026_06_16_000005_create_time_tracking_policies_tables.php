<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_tracking_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 70);
            $table->string('description', 140)->nullable();
            // clock_in_out | manual
            $table->string('submission_method')->default('clock_in_out');
            $table->boolean('allow_timesheet_editing')->default(false);
            $table->boolean('geofencing_enabled')->default(false);
            // work_schedule | custom
            $table->string('reminders_frequency')->default('work_schedule');
            $table->json('custom_reminder_times')->nullable();
            $table->timestamps();
        });

        Schema::create('time_tracking_policy_entity', function (Blueprint $table) {
            $table->foreignId('time_tracking_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_entity_id')->constrained()->cascadeOnDelete();
            $table->primary(['time_tracking_policy_id', 'company_entity_id'], 'tt_policy_entity_pk');
        });

        Schema::create('time_tracking_policy_department', function (Blueprint $table) {
            $table->foreignId('time_tracking_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['time_tracking_policy_id', 'department_id'], 'tt_policy_dept_pk');
        });

        // Geofenced sites enabled for this policy (presence = enabled).
        Schema::create('time_tracking_policy_site', function (Blueprint $table) {
            $table->foreignId('time_tracking_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_location_id')->constrained()->cascadeOnDelete();
            $table->primary(['time_tracking_policy_id', 'office_location_id'], 'tt_policy_site_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_tracking_policy_site');
        Schema::dropIfExists('time_tracking_policy_department');
        Schema::dropIfExists('time_tracking_policy_entity');
        Schema::dropIfExists('time_tracking_policies');
    }
};
