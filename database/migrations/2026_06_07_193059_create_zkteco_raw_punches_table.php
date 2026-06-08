<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_raw_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('zkteco_devices')->cascadeOnDelete();
            $table->integer('zkteco_uid');
            $table->string('zkteco_employee_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('punched_at');
            $table->tinyInteger('punch_state')->default(0); // 0=check_in,1=check_out,4=break_out,5=break_in
            $table->tinyInteger('verify_type')->default(1); // 1=finger,4=face,15=password
            $table->boolean('is_processed')->default(false);
            $table->boolean('is_duplicate')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'zkteco_uid', 'punched_at'], 'unique_device_uid_punch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_raw_punches');
    }
};
