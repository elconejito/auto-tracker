<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tire_replacements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('position', 20);
            $table->string('manufacturer', 150)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('size', 100)->nullable();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tire_replacements');
    }
};
