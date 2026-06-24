<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_teams', function (Blueprint $table) {
            // Menandai tim yang grup-nya ditetapkan manual (R15) atau lewat
            // undian (R16) agar tidak ditimpa ulang oleh auto-assign berbasis
            // seed saat pengaturan grup disimpan.
            $table->boolean('group_assigned_manually')->default(false)->after('group_label');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_teams', function (Blueprint $table) {
            $table->dropColumn('group_assigned_manually');
        });
    }
};
