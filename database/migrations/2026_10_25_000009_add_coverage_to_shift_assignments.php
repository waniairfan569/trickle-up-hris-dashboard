<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a single-day assignment as standing in for somebody who is away.
 *
 * Covering a shift is already expressible: a single-day assignment beats a
 * recurring one, so putting somebody on a date is all the rota needs. What was
 * missing was the reason — without it a cover shift and an ordinary one-off
 * look identical a week later, and nothing can tell whether a hole was filled.
 *
 * Nullable on purpose. Every assignment that exists today keeps meaning what
 * it meant, and a null here reads as "this was not cover", which is correct
 * for all of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->foreignId('covers_user_id')->nullable()->after('assigned_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->dropForeign(['covers_user_id']);
            $table->dropColumn('covers_user_id');
        });
    }
};
