<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('site_settings', 'lk_room_code_validation')) {
            return;
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('lk_room_code_validation')->default(1);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('site_settings', 'lk_room_code_validation')) {
            return;
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('lk_room_code_validation');
        });
    }
};
