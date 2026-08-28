<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin user/ledger search was scanning whole tables: wallet had no secondary
 * indexes (500k+ rows) and users.uid / game_challenges player ids were unindexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('users', 'idx_users_uid', ['uid']);
        $this->addIndex('users', 'idx_users_created_at', ['created_at']);
        $this->addIndex('users', 'idx_users_kyc_status', ['kyc_status']);
        $this->addIndex('users', 'idx_users_refer_by', ['refer_by']);
        $this->addIndex('users', 'idx_users_is_mobile_verified', ['is_mobile_verified']);

        $this->addIndex('wallet', 'idx_wallet_user_id_id', ['user_id', 'id']);
        $this->addIndex('wallet', 'idx_wallet_game_challenge_id', ['game_challenge_id']);
        $this->addIndex('wallet', 'idx_wallet_created_at', ['created_at']);
        $this->addIndex('wallet', 'idx_wallet_type_added_by', ['wallet_type', 'added_by']);
        $this->addIndex('wallet', 'idx_wallet_transaction_id', ['transaction_id']);

        $this->addIndex('game_challenges', 'idx_gc_uid', ['uid']);
        $this->addIndex('game_challenges', 'idx_gc_challenger_id', ['challenger_id']);
        $this->addIndex('game_challenges', 'idx_gc_opponent_id', ['opponent_id']);
        $this->addIndex('game_challenges', 'idx_gc_status', ['status']);
        $this->addIndex('game_challenges', 'idx_gc_created_at', ['created_at']);
        $this->addIndex('game_challenges', 'idx_gc_roomcode', ['roomcode']);
        $this->addIndex('game_challenges', 'idx_gc_closed_at', ['closed_at']);
    }

    public function down(): void
    {
        $this->dropIndex('users', 'idx_users_uid');
        $this->dropIndex('users', 'idx_users_created_at');
        $this->dropIndex('users', 'idx_users_kyc_status');
        $this->dropIndex('users', 'idx_users_refer_by');
        $this->dropIndex('users', 'idx_users_is_mobile_verified');

        $this->dropIndex('wallet', 'idx_wallet_user_id_id');
        $this->dropIndex('wallet', 'idx_wallet_game_challenge_id');
        $this->dropIndex('wallet', 'idx_wallet_created_at');
        $this->dropIndex('wallet', 'idx_wallet_type_added_by');
        $this->dropIndex('wallet', 'idx_wallet_transaction_id');

        $this->dropIndex('game_challenges', 'idx_gc_uid');
        $this->dropIndex('game_challenges', 'idx_gc_challenger_id');
        $this->dropIndex('game_challenges', 'idx_gc_opponent_id');
        $this->dropIndex('game_challenges', 'idx_gc_status');
        $this->dropIndex('game_challenges', 'idx_gc_created_at');
        $this->dropIndex('game_challenges', 'idx_gc_roomcode');
        $this->dropIndex('game_challenges', 'idx_gc_closed_at');
    }

    private function addIndex(string $table, string $name, array $columns): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $columns) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$name]);

        return count($rows) > 0;
    }
};
