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
        Schema::table('status_history', function (Blueprint $table) {
            // 🔥 DROP old integer column
            $table->dropColumn('added_by');
        });

        Schema::table('status_history', function (Blueprint $table) {
            // 🔥 ADD as string
            $table->string('added_by')->nullable()->after('remark');
        });
    }

    public function down(): void
    {
        Schema::table('status_history', function (Blueprint $table) {
            $table->dropColumn('added_by');
        });

        Schema::table('status_history', function (Blueprint $table) {
            $table->unsignedBigInteger('added_by')->nullable()->after('remark');
        });
    }
};
