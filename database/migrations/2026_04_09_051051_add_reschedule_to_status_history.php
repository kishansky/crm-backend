<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status_history', function (Blueprint $table) {
            $table->timestamp('reschedule_time')->nullable()->after('remark');
            $table->enum('shift', ['morning', 'noon', 'evening'])->nullable()->after('reschedule_time');
        });
    }

    public function down(): void
    {
        Schema::table('status_history', function (Blueprint $table) {
            $table->dropColumn(['reschedule_time', 'shift']);
        });
    }
};