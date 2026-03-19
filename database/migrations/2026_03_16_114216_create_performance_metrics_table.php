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
        Schema::create('performance_metrics', function (Blueprint $table) {

    $table->id('perf_id');

    $table->string('sales_person_id');

    $table->date('report_date');

    $table->integer('total_leads')->default(0);
    $table->integer('total_attended')->default(0);
    $table->integer('total_calls')->default(0);
    $table->integer('closed_ordered')->default(0);

    $table->timestamps();
    $table->softDeletes();

    $table->foreign('sales_person_id')
          ->references('sales_person_id')
          ->on('sales_team');

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};
