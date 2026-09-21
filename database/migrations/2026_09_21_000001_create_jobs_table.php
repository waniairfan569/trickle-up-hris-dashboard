<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The database queue's backing table. The core-tables migration created
 * job_batches and failed_jobs but not the `jobs` table itself, so every queued
 * job/listener/mailable (QUEUE_CONNECTION=database) failed with
 * "Table 'jobs' doesn't exist". This adds it (standard Laravel schema).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
