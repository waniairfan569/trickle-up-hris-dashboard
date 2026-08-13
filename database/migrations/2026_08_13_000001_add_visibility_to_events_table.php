<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Publish control: an event starts as a draft (hidden from employees)
            // and becomes visible only once an admin publishes it.
            if (!Schema::hasColumn('events', 'is_published')) {
                $table->boolean('is_published')->default(false)->after('status');
            }
            if (!Schema::hasColumn('events', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('is_published');
            }
            if (!Schema::hasColumn('events', 'published_by')) {
                $table->foreignId('published_by')->nullable()->after('published_at')
                    ->constrained('users')->nullOnDelete();
            }
            // Who can see it once published.
            if (!Schema::hasColumn('events', 'visibility')) {
                $table->enum('visibility', ['all', 'department', 'specific'])->default('all')->after('published_by');
            }
            // Pinned events surface at the top of the employee dashboard/calendar.
            if (!Schema::hasColumn('events', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('visibility');
            }
            // Whether to notify employees when this event is published.
            if (!Schema::hasColumn('events', 'notify_employees')) {
                $table->boolean('notify_employees')->default(true)->after('is_pinned');
            }
        });

        // Events created before this feature were already shown to everyone, so
        // publish them to preserve that visibility (new events default to draft).
        \Illuminate\Support\Facades\DB::table('events')
            ->whereNull('published_at')
            ->update([
                'is_published' => true,
                'published_at' => now(),
                'visibility' => 'all',
            ]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'published_by')) {
                $table->dropConstrainedForeignId('published_by');
            }
            foreach (['is_published', 'published_at', 'visibility', 'is_pinned', 'notify_employees'] as $col) {
                if (Schema::hasColumn('events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
