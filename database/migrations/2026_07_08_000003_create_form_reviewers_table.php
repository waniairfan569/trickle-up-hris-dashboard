<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('company_forms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['form_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_reviewers');
    }
};
