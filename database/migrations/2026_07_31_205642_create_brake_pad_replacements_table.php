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
        Schema::create('brake_pad_replacements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('position', 20);
            $table->string('manufacturer', 150)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brake_pad_replacements');
    }
};
