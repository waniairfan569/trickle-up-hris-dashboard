<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_status', ['invited', 'active', 'suspended', 'deactivated'])->default('invited')->after('status');
            $table->string('invitation_token')->nullable(); // Raw token stored temporarily, cleared after use. Note: Usually not stored in DB, but requirement says "raw token stored temporarily, cleared after use" so we'll add it.
            $table->string('invitation_token_hash')->nullable()->unique();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamp('invitation_accepted_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn([
                'account_status',
                'invitation_token',
                'invitation_token_hash',
                'invitation_sent_at',
                'invitation_expires_at',
                'invitation_accepted_at',
                'must_change_password',
                'invited_by',
            ]);
        });
    }
};
