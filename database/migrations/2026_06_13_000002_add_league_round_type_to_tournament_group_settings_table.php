<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_group_settings', function (Blueprint $table) {
            // R11 — pilihan format liga:
            //   single = setengah kompetisi (round robin sekali)
            //   double = kompetisi penuh / kandang-tandang (round robin dua kali,
            //            putaran kedua home/away dibalik)
            $table->string('league_round_type')->default('single');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_group_settings', function (Blueprint $table) {
            $table->dropColumn('league_round_type');
        });
    }
};
