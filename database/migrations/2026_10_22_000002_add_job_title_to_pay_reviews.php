<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a pay review carry the job title change that came with it.
 *
 * A promotion is usually both — new title and new money — but the two were
 * recorded in different places: the money as a review, the title as a profile
 * field edited on its own. That meant a promotion raised a salary letter with
 * the old title on it, or no letter at all if only the title moved.
 *
 * Both titles are kept, because the employee's own job_title is overwritten by
 * the change: without the "previous" here, what they were promoted FROM is
 * gone the moment it is applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_reviews', function (Blueprint $table) {
            $table->string('previous_job_title')->nullable()->after('review_type');
            $table->string('new_job_title')->nullable()->after('previous_job_title');
        });
    }

    public function down(): void
    {
        Schema::table('pay_reviews', function (Blueprint $table) {
            $table->dropColumn(['previous_job_title', 'new_job_title']);
        });
    }
};
