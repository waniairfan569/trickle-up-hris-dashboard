<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('logo_url', 500)->nullable();
            $table->string('timezone', 100)->default('UTC');
            $table->string('industry', 100)->nullable();
            $table->string('size', 50)->nullable();
            $table->string('website', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('companies');
    }
};
