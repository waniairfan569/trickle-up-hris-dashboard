<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-only conduct / behaviour log per employee. HR records incidents or notes
 * (with a date and category) that surface only on the admin side and are included
 * in the employee's time-tracking report.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('conduct_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();     // the employee
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete(); // who logged it
            $table->date('occurred_on');
            $table->string('category')->nullable();
            $table->text('note');
            $table->timestamps();

            $table->index(['user_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conduct_notes');
    }
};
