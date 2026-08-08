<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('registration_bonus_pending')->default(false);
        });

        // Existing members must not receive the welcome bonus on next OTP login.
        DB::table('users')->update(['registration_bonus_pending' => false]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('registration_bonus_pending');
        });
    }
};
