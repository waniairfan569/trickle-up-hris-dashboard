<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captures real application errors (5xx / unexpected exceptions) so operators
 * can see what clients hit without reading server logs. De-duplicated by
 * fingerprint — repeats bump `occurrences` instead of piling up rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_errors', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint')->index();
            $table->string('exception');           // exception class
            $table->text('message');
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->string('url', 1024)->nullable();
            $table->string('method', 10)->nullable();
            $table->integer('status_code')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->text('trace')->nullable();     // first lines of the stack trace
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();                   // updated_at = last seen
            $table->index(['resolved_at', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_errors');
    }
};
