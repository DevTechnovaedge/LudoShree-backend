<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * King (Daddy King) WebSocket sync: link game challenges to network tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_challenges', function (Blueprint $table) {
            if (! Schema::hasColumn('game_challenges', 'game_source')) {
                $table->string('game_source', 20)->default('local')->comment('local | daddy_king')->after('status');
            }

            if (! Schema::hasColumn('game_challenges', 'king_table_id')) {
                $table->string('king_table_id', 50)->nullable()->comment('King network table id e.g. DK-2-3')->after('game_source');
                $table->index('king_table_id', 'idx_gc_king_table_id');
            }

            if (! Schema::hasColumn('game_challenges', 'king_sync_status')) {
                $table->string('king_sync_status', 20)->nullable()->comment('pending | synced | failed')->after('king_table_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_challenges', function (Blueprint $table) {
            if (Schema::hasColumn('game_challenges', 'king_table_id')) {
                $table->dropIndex('idx_gc_king_table_id');
            }

            foreach (['game_source', 'king_table_id', 'king_sync_status'] as $column) {
                if (Schema::hasColumn('game_challenges', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
