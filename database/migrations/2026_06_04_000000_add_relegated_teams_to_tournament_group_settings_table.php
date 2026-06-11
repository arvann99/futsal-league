<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournament_group_settings', function (Blueprint $table) {
            $table->json('relegated_teams')->nullable()->after('qualified_teams');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_group_settings', function (Blueprint $table) {
            $table->dropColumn('relegated_teams');
        });
    }
};
