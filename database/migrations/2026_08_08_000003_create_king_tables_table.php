<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror of every table known on the King (Daddy King) network.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('king_tables')) {
            return;
        }

        Schema::create('king_tables', function (Blueprint $table) {
            $table->id();
            $table->string('king_table_id', 50)->unique('idx_kt_king_table_id');
            $table->string('origin', 10)->default('remote')->comment('local = created by us, remote = created on another platform');
            $table->unsignedBigInteger('game_challenge_id')->nullable()->index('idx_kt_game_challenge_id');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('Pending')->index('idx_kt_status')->comment('Pending | Start | View | Completed | Deleted | Missing');
            $table->string('created_by_id', 64)->nullable();
            $table->string('created_by_name', 191)->nullable();
            $table->string('joined_by_id', 64)->nullable();
            $table->string('joined_by_name', 191)->nullable();
            $table->string('room_code', 20)->nullable();
            $table->string('creator_result', 20)->nullable();
            $table->string('joiner_result', 20)->nullable();
            $table->text('raw')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('king_tables');
    }
};
