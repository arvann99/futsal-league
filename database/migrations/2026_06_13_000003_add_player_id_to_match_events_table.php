<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            // R19 — hubungkan event ke pemain asli (tournament_team_players).
            // Nullable: event lama / event tanpa roster tetap valid lewat
            // player_name string. nullOnDelete agar hapus pemain tidak
            // menghapus riwayat event pertandingan.
            $table->foreignId('player_id')
                ->nullable()
                ->after('player_name')
                ->constrained('tournament_team_players')
                ->nullOnDelete();

            $table->index(['player_id']);
        });
    }

    public function down(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
            $table->dropIndex(['player_id']);
            $table->dropColumn('player_id');
        });
    }
};
