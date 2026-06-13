<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // NULL = single match; 1/2 = leg pada tie Home & Away
            $table->unsignedTinyInteger('leg')->nullable()->after('is_third_place');
            $table->unsignedSmallInteger('home_penalty_score')->nullable()->after('away_score');
            $table->unsignedSmallInteger('away_penalty_score')->nullable()->after('home_penalty_score');

            $table->index(['tournament_id', 'bracket_match_id']);
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['tournament_id', 'bracket_match_id']);
            $table->dropColumn(['leg', 'home_penalty_score', 'away_penalty_score']);
        });
    }
};
