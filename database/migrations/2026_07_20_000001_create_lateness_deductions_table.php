<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lateness_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->decimal('days_deducted', 4, 1)->default(0);   // total applied this month (0 / 0.5 / 1.0)
            $table->foreignId('policy_id')->nullable()->constrained('time_off_policies')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lateness_deductions');
    }
};
