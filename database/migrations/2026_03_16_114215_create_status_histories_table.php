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
        Schema::create('status_history', function (Blueprint $table) {

    $table->id('history_id');

    $table->string('lead_id');

    $table->string('status_type');
    $table->text('remark')->nullable();

    $table->timestamp('updated_at');

    $table->softDeletes();

    $table->foreign('lead_id')
          ->references('lead_id')
          ->on('leads_master');

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};
