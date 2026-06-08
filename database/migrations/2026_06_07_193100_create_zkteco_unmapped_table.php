<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_unmapped', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('zkteco_devices')->cascadeOnDelete();
            $table->integer('zkteco_uid');
            $table->string('zkteco_employee_id');
            $table->integer('punch_count')->default(1);
            $table->dateTime('first_seen');
            $table->dateTime('last_seen');
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'zkteco_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_unmapped');
    }
};
