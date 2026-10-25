<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conversation between two people, with a time on it.
 *
 * The company calendar already exists, but it holds announcements: a date with
 * no time, published to an audience, pinned if it matters. None of that fits a
 * one-to-one, which is private to two people, happens at 14:30 rather than "on
 * Tuesday", and has to be agreed before it is a fixture. Putting it in `events`
 * would have meant a type column and then an exception at every place that
 * publishes, pins or scopes by visibility.
 *
 * `about_type`/`about_id` is the part that earns its keep: this product already
 * creates the occasions for these conversations — a probation review falling
 * due, a lateness review, a return to work — and a meeting booked from one of
 * them stays attached to it, so the record of the conversation is not somewhere
 * else from the reason for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_to_ones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            // The employee it is with, and whoever is holding it. Both are
            // users, and either may be the one who asked for it.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);

            $table->string('subject');
            $table->text('agenda')->nullable();

            $table->string('location')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('meeting_link')->nullable();

            // proposed | confirmed | declined | held | cancelled | missed
            $table->string('status')->default('proposed');

            // Who put this time forward. After a counter-proposal this changes
            // hands, which is how the page knows whose turn it is to answer.
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();

            $table->timestamp('held_at')->nullable();
            $table->text('outcome')->nullable();

            // Only the two people in the room see this, never the employee.
            $table->text('private_note')->nullable();

            $table->nullableMorphs('about');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The two questions the pages actually ask: "what is coming up for
            // me", and "what is outstanding".
            $table->index(['user_id', 'scheduled_at']);
            $table->index(['manager_id', 'scheduled_at']);
            $table->index(['tenant_id', 'status']);
        });

        // The same shape as probation_events, because it answers the same
        // question: who did what to this, and when.
        Schema::create('one_to_one_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('one_to_one_id')->constrained()->cascadeOnDelete();
            // proposed | confirmed | declined | rescheduled | held | cancelled | note
            $table->string('action');
            $table->dateTime('new_time')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_to_one_events');
        Schema::dropIfExists('one_to_ones');
    }
};
