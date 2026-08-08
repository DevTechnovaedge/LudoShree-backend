<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * King (Daddy King) WebSocket sync: proxy (ghost) accounts for network players.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_king_player')) {
                $table->tinyInteger('is_king_player')->default(0)->comment('1 = proxy account for a Daddy King network player');
            }

            if (! Schema::hasColumn('users', 'king_player_id')) {
                $table->string('king_player_id', 64)->nullable()->comment('External player id e.g. 2-5');
                $table->unique('king_player_id', 'idx_users_king_player_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'king_player_id')) {
                $table->dropUnique('idx_users_king_player_id');
            }

            foreach (['is_king_player', 'king_player_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
