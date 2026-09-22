<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of generated approved-overtime reports, so finance has a trail of which
 * periods have been exported (and the totals at the time). One row per export.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('overtime_report_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('label')->nullable();
            $table->string('format', 8)->default('pdf'); // pdf | csv
            $table->unsignedInteger('entry_count')->default(0);
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_report_runs');
    }
};
