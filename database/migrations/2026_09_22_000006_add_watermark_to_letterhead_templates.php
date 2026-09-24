<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional faint page watermark (e.g. a large brand mark behind the body text)
 * for a letterhead, with a configurable opacity.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('letterhead_templates', function (Blueprint $table) {
            $table->string('watermark_image_path')->nullable()->after('footer_image_path');
            $table->decimal('watermark_opacity', 4, 3)->default(0.06)->after('watermark_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('letterhead_templates', function (Blueprint $table) {
            $table->dropColumn(['watermark_image_path', 'watermark_opacity']);
        });
    }
};
