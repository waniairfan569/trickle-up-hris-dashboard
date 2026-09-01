<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // owner = full power; support = view + impersonate + restorative actions.
            $table->string('operator_role')->nullable()->after('is_operator');
        });

        // Every existing operator becomes an Owner.
        DB::table('users')->where('is_operator', true)->update(['operator_role' => 'owner']);

        Schema::create('operator_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');                 // impersonate | operator_added | operator_role_changed | operator_revoked
            $table->string('description');
            $table->foreignId('target_tenant_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_audits');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('operator_role');
        });
    }
};
