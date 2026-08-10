<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // code_provided is now encrypted at rest; ciphertext is longer than the
    // original varchar(255) (email+password combos overflow it), so widen to TEXT.
    public function up(): void
    {
        Schema::table('code_requests', function (Blueprint $table) {
            $table->text('code_provided')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('code_requests', function (Blueprint $table) {
            $table->string('code_provided')->nullable()->change();
        });
    }
};
