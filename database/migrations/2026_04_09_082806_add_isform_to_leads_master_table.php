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
        Schema::table('leads_master', function (Blueprint $table) {
            $table->boolean('is_form')->default(false)->after('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('leads_master', function (Blueprint $table) {
            $table->dropColumn('isform');
        });
    }
};
