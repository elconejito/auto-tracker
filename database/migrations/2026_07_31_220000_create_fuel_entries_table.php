<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->dateTime('fueled_at');
            $table->unsignedBigInteger('odometer');
            $table->decimal('gallons', 10, 3);
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->decimal('price_per_gallon', 10, 3)->nullable();
            $table->unsignedBigInteger('miles_driven')->nullable();
            $table->decimal('calculated_mpg', 8, 2)->nullable();
            $table->string('station', 150)->nullable();
            $table->string('fuel_grade', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'fueled_at', 'id']);
            $table->index(['vehicle_id', 'odometer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_entries');
    }
};
