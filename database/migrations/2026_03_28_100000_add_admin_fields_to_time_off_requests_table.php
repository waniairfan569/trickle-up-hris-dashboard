<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_admin')->nullable();
            $table->unsignedBigInteger('updated_by_admin')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('override_reason')->nullable();
            $table->boolean('is_admin_created')->default(false);
            $table->boolean('is_overridden')->default(false);
            $table->string('original_status', 50)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->text('revoke_reason')->nullable();

            $table->foreign('created_by_admin')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by_admin')->references('id')->on('users')->onDelete('set null');
            $table->foreign('revoked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->dropForeign(['created_by_admin']);
            $table->dropForeign(['updated_by_admin']);
            $table->dropForeign(['revoked_by']);
            
            $table->dropColumn([
                'created_by_admin',
                'updated_by_admin',
                'admin_note',
                'override_reason',
                'is_admin_created',
                'is_overridden',
                'original_status',
                'revoked_at',
                'revoked_by',
                'revoke_reason',
            ]);
        });
    }
};
