<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What should happen when an employee changes this field about themselves.
 *
 * Until now there were two answers: they can edit it, or they cannot. Nothing
 * in between — so a change of address and a change of medical conditions were
 * treated the same, and both were saved with nobody told.
 *
 *   none    save it, as today
 *   notify  save it, and tell HR it changed
 *   review  do not save it yet; HR approves it first
 *
 * Defaults to "none" everywhere, so nothing behaves differently until a
 * workspace sets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_fields', function (Blueprint $table) {
            $table->string('change_action', 10)->default('none')->after('employee_can_edit');
        });
    }

    public function down(): void
    {
        Schema::table('profile_fields', function (Blueprint $table) {
            $table->dropColumn('change_action');
        });
    }
};
