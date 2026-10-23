<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every request that reaches /iclock, recognised or not.
 *
 * A device is only recorded as "seen" once we can tie a request to a serial
 * number. A device posting without one, or to a path we do not handle, is
 * answered and forgotten — so "never heard from" could equally mean "arriving
 * in a shape we ignore", and there was no way to tell those apart.
 *
 * Deliberately raw: the path, what came with it, who from. Pruned as it goes,
 * because a device polls every ten seconds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_contact_log', function (Blueprint $table) {
            $table->id();
            $table->string('method', 8);
            $table->string('path', 191);
            $table->string('query', 500)->nullable();
            $table->string('serial', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedInteger('body_bytes')->default(0);
            $table->boolean('handled')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_contact_log');
    }
};
