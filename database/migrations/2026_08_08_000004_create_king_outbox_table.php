<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound message queue for the King (Daddy King) WebSocket.
 * HTTP requests only insert rows here; the king:listen daemon sends them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('king_outbox')) {
            return;
        }

        Schema::create('king_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50);
            $table->text('payload')->nullable();
            $table->string('king_table_id', 50)->nullable()->index('idx_ko_king_table_id');
            $table->unsignedBigInteger('game_challenge_id')->nullable()->index('idx_ko_game_challenge_id');
            $table->unsignedBigInteger('acting_user_id')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending | sent | success | failed | skipped');
            $table->integer('attempts')->default(0);
            $table->text('response')->nullable();
            $table->string('error', 500)->nullable();
            $table->dateTime('available_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at'], 'idx_ko_status_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('king_outbox');
    }
};
