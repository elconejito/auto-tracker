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
        Schema::create('oil_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('viscosity', 50)->nullable();
            $table->decimal('quantity', 8, 3)->nullable();
            $table->boolean('filter_replaced')->default(false);
            $table->string('filter_part_number', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oil_changes');
    }
};
