<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a change to this field is told to the employee's line manager as
 * well as to HR.
 *
 * Changes have gone to the people who hold employee records, and only them.
 * That is right for a bank detail and arguable for an emergency contact — but
 * the brief asks for a changed health record to reach a manager, and a manager
 * who does not know somebody's condition has changed cannot act on it.
 *
 * It is a choice rather than a rule because the risk runs both ways: medical
 * details reaching a line manager automatically is exactly the kind of thing a
 * client may be required not to do. Off everywhere until somebody turns it on,
 * field by field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_fields', function (Blueprint $table) {
            $table->boolean('notify_line_manager')->default(false)->after('change_action');
        });
    }

    public function down(): void
    {
        Schema::table('profile_fields', function (Blueprint $table) {
            $table->dropColumn('notify_line_manager');
        });
    }
};
