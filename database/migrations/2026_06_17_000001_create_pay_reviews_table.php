<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date');
            $table->decimal('previous_salary', 12, 2)->nullable();
            $table->decimal('new_salary', 12, 2);
            $table->decimal('increment_amount', 12, 2)->default(0);
            $table->decimal('increment_percent', 8, 2)->nullable();
            $table->integer('months_since_last')->nullable();
            $table->string('currency', 8)->default('PKR');
            $table->string('pay_type')->default('salary');         // salary | hourly | contract
            $table->string('pay_schedule')->default('monthly');    // monthly | weekly | ...
            $table->string('review_type')->default('annual');      // annual | mid_year | promotion | correction | market_adjustment | joining
            $table->string('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_reviews');
    }
};
