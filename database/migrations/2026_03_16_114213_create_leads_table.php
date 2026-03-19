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
        Schema::create('leads_master', function (Blueprint $table) {

    $table->string('lead_id')->primary();

    $table->dateTime('timestamp');
    $table->string('source')->nullable();
    $table->string('company_name')->nullable();
    $table->string('contact_person');

    $table->string('phone_number');
    $table->string('email')->nullable();

    $table->text('enquiry_description')->nullable();

    $table->string('assigned_to');

    $table->foreign('assigned_to')
          ->references('sales_person_id')
          ->on('sales_team');

    $table->timestamps();
    $table->softDeletes();

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
