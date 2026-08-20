<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide work-from-home days: specific dates (e.g. some Fridays) on which
 * EVERY employee works remotely, so their clock-in auto-switches to the
 * dashboard for that date without touching each person's settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_wfh_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->date('date')->index();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_wfh_days');
    }
};
