<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('performance_reviews');

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('review_cycles')->onDelete('cascade');
            $table->foreignId('reviewee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('type', ['self', 'manager', 'peer'])->default('self');
            $table->enum('status', ['draft', 'submitted', 'shared', 'signed'])->default('draft');
            $table->json('content')->nullable();
            
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            
            // Unique constraint to prevent multiple self/manager reviews per cycle for the same user
            $table->unique(['cycle_id', 'reviewee_id', 'reviewer_id', 'type'], 'perf_review_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
        // Fallback to old schema isn't necessary for this one-way rebuild.
    }
};
