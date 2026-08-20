<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lateness-penalty reversal workflow: an employee can appeal a penalty with a
 * reason; an admin can approve (revert — restoring the deducted days) or decline
 * with a response, or revert directly. A reverted month is flagged so the
 * recurring lateness sync never re-applies it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lateness_deductions', function (Blueprint $table) {
            $table->timestamp('reverted_at')->nullable()->after('warning_sent_at');
            $table->unsignedBigInteger('reverted_by')->nullable()->after('reverted_at');

            $table->string('reversal_status')->nullable()->after('reverted_by'); // requested | approved | rejected
            $table->text('reversal_reason')->nullable()->after('reversal_status');       // employee's reason
            $table->text('reversal_response')->nullable()->after('reversal_reason');     // admin's response
            $table->timestamp('reversal_requested_at')->nullable()->after('reversal_response');
            $table->timestamp('reversal_reviewed_at')->nullable()->after('reversal_requested_at');
            $table->unsignedBigInteger('reversal_reviewed_by')->nullable()->after('reversal_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('lateness_deductions', function (Blueprint $table) {
            $table->dropColumn([
                'reverted_at', 'reverted_by', 'reversal_status', 'reversal_reason',
                'reversal_response', 'reversal_requested_at', 'reversal_reviewed_at', 'reversal_reviewed_by',
            ]);
        });
    }
};
