<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // R18 — berkas verifikasi per tim (KTP, akta lahir, surat keterangan, dll).
        Schema::create('team_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('document_name');
            $table->string('document_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index(['team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_verification_documents');
    }
};
