<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers that we chased somebody about something they have not acknowledged.
 *
 * This cannot live on the acknowledgement tables themselves. A policy has a row
 * per assignee to stamp, but a company document only gets a row once the person
 * has actually acknowledged — so for a document the people we need to chase are
 * exactly the ones with no row to write to. One table beside both, keyed
 * polymorphically, covers either kind and anything added later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acknowledgement_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('acknowledgeable', 'ack_reminders_target');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->unsignedSmallInteger('times_sent')->default(1);
            $table->timestamps();

            // One row per person per thing; sent_at and times_sent move on it.
            $table->unique(
                ['acknowledgeable_type', 'acknowledgeable_id', 'user_id'],
                'ack_reminders_unique_target'
            );
            $table->index(['tenant_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acknowledgement_reminders');
    }
};
