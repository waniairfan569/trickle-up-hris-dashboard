<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('form_submissions', 'review_status')) {
                $table->enum('review_status', ['pending', 'approved', 'rejected'])->default('pending')->after('status');
            }
            if (!Schema::hasColumn('form_submissions', 'review_note')) {
                $table->text('review_note')->nullable()->after('review_status');
            }
            if (!Schema::hasColumn('form_submissions', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('form_submissions', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('form_submissions', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            foreach (['review_status', 'review_note', 'reviewed_at'] as $col) {
                if (Schema::hasColumn('form_submissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
