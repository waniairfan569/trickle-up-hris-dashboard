<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable saved signatures ("email signatures") — draw/type/upload once,
     * then drop onto documents in the builder to stamp automatically.
     */
    public function up(): void
    {
        Schema::create('signature_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->longText('image_data');          // PNG data URL of the signature
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Let a placed field reference a saved signature (field_type = 'saved_signature').
        Schema::table('document_template_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('document_template_fields', 'signature_template_id')) {
                $table->unsignedBigInteger('signature_template_id')->nullable()->after('assignee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_template_fields', function (Blueprint $table) {
            if (Schema::hasColumn('document_template_fields', 'signature_template_id')) {
                $table->dropColumn('signature_template_id');
            }
        });
        Schema::dropIfExists('signature_templates');
    }
};
