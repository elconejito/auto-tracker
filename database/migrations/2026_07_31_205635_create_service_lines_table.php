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
        Schema::create('service_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('maintenance_event_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('serviceable');
            $table->decimal('line_cost', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_lines');
    }
};
