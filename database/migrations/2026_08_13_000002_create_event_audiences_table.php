<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_audiences')) {
            return;
        }

        Schema::create('event_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            // 'department' → audience_id is a departments.id; 'user' → users.id.
            $table->enum('audience_type', ['department', 'user']);
            $table->unsignedBigInteger('audience_id');
            $table->timestamps();

            $table->index(['event_id', 'audience_type']);
            $table->index(['audience_type', 'audience_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_audiences');
    }
};
