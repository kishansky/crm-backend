<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('needs', function (Blueprint $table) {
            $table->id();
            $table->string('lead_id');
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();

            $table->string('property_type')->nullable(); // Land, Plot, House, Flat
            $table->decimal('min_area', 10, 2)->nullable();
            $table->decimal('max_area', 10, 2)->nullable();
            $table->string('area_unit')->default('sqft'); // sqft, acre, bigha
            $table->decimal('min_budget', 12, 2)->nullable();
            $table->decimal('max_budget', 12, 2)->nullable();

            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')
                  ->references('lead_id')
                  ->on('leads_master')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('needs');
    }
};