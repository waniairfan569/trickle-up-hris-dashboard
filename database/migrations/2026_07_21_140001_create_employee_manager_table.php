<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additional managers for an employee (beyond the single primary
     * users.manager_id). An employee can report to several managers; each row
     * links the employee (user_id) to one manager (manager_id). The primary
     * manager stays on users.manager_id (org chart parent); these are extras
     * who also get manager rights over the employee.
     */
    public function up(): void
    {
        Schema::create('employee_manager', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();     // the employee (report)
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();  // an additional manager
            $table->timestamps();

            $table->unique(['user_id', 'manager_id']);
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_manager');
    }
};
