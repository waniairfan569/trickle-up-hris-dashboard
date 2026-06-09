<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable()->default('PK');
            $table->string('country_name')->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('employee_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_locations');
    }
};
