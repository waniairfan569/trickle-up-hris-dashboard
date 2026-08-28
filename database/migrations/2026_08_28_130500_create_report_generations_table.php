<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('report_scope');          // single | all
            $table->string('report_type');           // monthly | yearly | mid_year | custom
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_name')->nullable(); // snapshot — survives employee deletion

            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('half')->nullable();       // first | second
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();

            $table->string('period_label');           // e.g. "June 2026"
            $table->string('output');                 // preview | pdf (what was done first)
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_generations');
    }
};
