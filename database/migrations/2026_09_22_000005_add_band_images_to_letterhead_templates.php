<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional pre-designed header/footer band images for a letterhead (e.g. a
 * ready-made brand wordmark and a full-width contact strip exported from a docx).
 * When set they render in place of the structured header/footer text.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('letterhead_templates', function (Blueprint $table) {
            $table->string('header_image_path')->nullable()->after('logo_path');
            $table->string('footer_image_path')->nullable()->after('header_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('letterhead_templates', function (Blueprint $table) {
            $table->dropColumn(['header_image_path', 'footer_image_path']);
        });
    }
};
