<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // R22 — paket langganan + admin root.
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan')->default('free')->after('avatar'); // free|pro|ultimate
            $table->boolean('is_root')->default(false)->after('plan')->index();
        });

        // Admin pertama dijadikan ROOT aplikasi + paket ultimate agar tidak
        // pernah keblok limit & bisa meng-ACC pembayaran. Pilih akun
        // admin@gmail.com bila ada; jika tidak, jatuh ke user id terkecil.
        // Hanya SATU user yang ditandai (hindari banyak root tak sengaja).
        $rootId = DB::table('users')->where('email', 'admin@gmail.com')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');

        if ($rootId) {
            DB::table('users')->where('id', $rootId)
                ->update(['is_root' => true, 'plan' => 'ultimate']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['plan', 'is_root']);
        });
    }
};
