<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event / consistency audit log for the King (Daddy King) sync.
 * The admin panel reads warnings + errors from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('king_event_logs')) {
            return;
        }

        Schema::create('king_event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10)->default('in')->comment('in | out | sys');
            $table->string('uri', 64)->nullable();
            $table->string('level', 10)->default('info')->index('idx_kel_level')->comment('info | warning | error');
            $table->string('message', 500)->nullable();
            $table->mediumText('payload')->nullable();
            $table->timestamp('created_at')->nullable()->index('idx_kel_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('king_event_logs');
    }
};
