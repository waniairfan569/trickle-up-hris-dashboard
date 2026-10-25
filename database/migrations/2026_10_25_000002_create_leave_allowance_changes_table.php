<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When somebody's leave allowance changed, and what it changed from.
 *
 * Pro-rata has only ever been worked out for joiners, because a joining date
 * is the one date the system holds. An allowance that changes part-way through
 * a year — going part-time, coming back full-time, a re-grade — applies to the
 * whole year as though it had always been that, so somebody who drops to three
 * days a week in July keeps a full-time allowance for the months before and
 * after alike.
 *
 * policy_user.effective_from cannot answer this: it records when the policy was
 * assigned, and in practice equals assigned_at on every row. Reading it as "the
 * rate changed here" would wrongly pro-rate assignments that never changed.
 *
 * Nothing reads this table until it has rows, and rows only appear from the
 * next time an allowance is actually changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_allowance_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained('time_off_policies')->cascadeOnDelete();
            $table->decimal('previous_days', 6, 2)->nullable();
            $table->decimal('new_days', 6, 2);
            $table->date('effective_from');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'policy_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_allowance_changes');
    }
};
