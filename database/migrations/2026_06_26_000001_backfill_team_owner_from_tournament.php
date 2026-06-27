<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaikan data: tim yang dibuat lewat alur "peserta turnamen" sebelum kolom
 * created_by diisi (lihat TournamentParticipantController) tertinggal NULL,
 * sehingga middleware `owns` (EnsureResourceOwnership) menolak semua aksi
 * terhadap tim tsb dengan 403 — termasuk reset-token.
 *
 * Backfill created_by dari pemilik turnamen tempat tim tersebut terdaftar.
 * Fallback ke admin pertama bila tim sama sekali tak terhubung turnamen,
 * konsisten dengan keputusan backfill R21.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Tim yatim yang terhubung ke turnamen → ambil pemilik turnamennya.
        //    Bila tim ada di beberapa turnamen, pakai owner turnamen dengan id
        //    terkecil (deterministik). Sumber data konsisten karena pivot tim
        //    selalu mengarah ke turnamen milik admin yang sama.
        $rows = DB::table('teams')
            ->join('tournament_teams', 'tournament_teams.team_id', '=', 'teams.id')
            ->join('tournaments', 'tournaments.id', '=', 'tournament_teams.tournament_id')
            ->whereNull('teams.created_by')
            ->whereNotNull('tournaments.created_by')
            ->orderBy('tournaments.id')
            ->get(['teams.id as team_id', 'tournaments.created_by as owner_id']);

        $ownerByTeam = [];
        foreach ($rows as $row) {
            // first wins (orderBy tournaments.id) → deterministik.
            $ownerByTeam[$row->team_id] ??= $row->owner_id;
        }

        foreach ($ownerByTeam as $teamId => $ownerId) {
            DB::table('teams')->where('id', $teamId)->update(['created_by' => $ownerId]);
        }

        // 2) Sisa tim yatim tanpa turnamen → admin pertama (fallback R21).
        $firstAdminId = DB::table('users')->orderBy('id')->value('id');
        if ($firstAdminId) {
            DB::table('teams')->whereNull('created_by')->update(['created_by' => $firstAdminId]);
        }
    }

    public function down(): void
    {
        // Backfill data tidak di-rollback: tidak ada cara aman membedakan
        // owner hasil backfill dari owner yang sudah benar sebelumnya.
    }
};
