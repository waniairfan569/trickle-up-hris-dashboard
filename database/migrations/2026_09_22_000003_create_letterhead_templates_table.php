<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable letterhead templates (branded header + footer) that wrap generated HR
 * documents. One is the default; individual documents can override it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('letterhead_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->foreignId('company_entity_id')->nullable()->constrained('company_entities')->nullOnDelete();
            $table->string('logo_path')->nullable();          // uploaded logo (else the workspace logo)
            $table->string('company_name');                    // big header text
            $table->string('company_suffix')->nullable();      // subtitle under the name
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('header_bg', 9)->default('#FFFDF5');
            $table->string('header_accent', 9)->default('#fcd82f');
            $table->string('footer_bg', 9)->default('#1F5FD6');
            $table->string('footer_text', 9)->default('#ffffff');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letterhead_templates');
    }
};
