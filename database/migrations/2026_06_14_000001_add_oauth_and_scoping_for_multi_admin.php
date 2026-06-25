<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // R21 — dukungan Google OAuth di tabel users.
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('google_id');
        });

        // Password boleh kosong untuk user yang hanya login via Google.
        // (MySQL: ubah kolom existing jadi nullable.)
        DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NULL');

        // R21 — scoping tim per admin.
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('notes')
                ->constrained('users')->nullOnDelete();
            $table->index('created_by');
        });

        // Backfill: data lama jadi milik admin pertama (id terkecil) — sesuai
        // keputusan agar PON 2026 & tim lama tetap terlihat admin#1.
        $firstAdminId = DB::table('users')->orderBy('id')->value('id');

        if ($firstAdminId) {
            DB::table('tournaments')->whereNull('created_by')->update(['created_by' => $firstAdminId]);
            DB::table('teams')->whereNull('created_by')->update(['created_by' => $firstAdminId]);
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['created_by']);
            $table->dropColumn('created_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar']);
        });

        // password tidak dikembalikan ke NOT NULL otomatis (data Google user
        // bisa null) — biarkan nullable agar rollback aman.
    }
};
