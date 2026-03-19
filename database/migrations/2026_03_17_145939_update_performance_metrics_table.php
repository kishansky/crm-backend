<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_metrics', function (Blueprint $table) {

    $table->index('sales_person_id');
    $table->index('report_date');

    $table->unique(['sales_person_id', 'report_date'], 'unique_sales_date');
});
    }

    public function down(): void
    {
        Schema::table('performance_metrics', function (Blueprint $table) {

            $table->dropUnique('unique_sales_date');
            $table->dropIndex(['sales_person_id']);
            $table->dropIndex(['report_date']);

            $table->dropForeign(['sales_person_id']);
        });
    }
};