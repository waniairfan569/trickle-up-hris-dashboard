<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linked_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('name');
            $table->text('url');
            $table->text('description')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('provider')->nullable();          // google | excel | airtable | link
            $table->unsignedInteger('opens_count')->default(0);
            $table->timestamp('last_opened_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linked_sheets');
    }
};
