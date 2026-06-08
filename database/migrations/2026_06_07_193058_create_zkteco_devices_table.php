<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Main Entrance');
            $table->string('ip_address');
            $table->integer('port')->default(4370);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->enum('last_sync_status', ['success', 'failed', 'never'])->default('never');
            $table->text('last_sync_message')->nullable();
            $table->integer('total_records_synced')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_devices');
    }
};
