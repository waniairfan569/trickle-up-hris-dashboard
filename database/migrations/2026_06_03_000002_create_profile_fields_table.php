<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('profile_sections')->onDelete('cascade');
            $table->string('name');
            $table->string('key')->unique();
            $table->enum('type', [
                'text', 'textarea', 'number', 'date', 'date_range', 
                'dropdown', 'multi_select', 'checkbox', 'phone', 
                'email', 'url', 'file', 'currency', 'employee_lookup', 
                'department_lookup'
            ]);
            $table->json('options')->nullable();
            $table->string('placeholder')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->enum('visibility', ['public', 'internal', 'private', 'manager'])->default('public');
            $table->boolean('employee_can_edit')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // key index is created via unique() constraint
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_fields');
    }
};
