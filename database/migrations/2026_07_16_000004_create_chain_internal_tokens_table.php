<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Borsanın KENDİ ürettiği zincir token'ları (çekimdeki para üstü/change ve
        // merge çıktıları). Bunlar deposit adresine geri döner ama yatırma DEĞİLDİR;
        // deposit tarayıcısı bu token'ları kredilememelidir.
        Schema::create('chain_internal_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('asset', 20);
            $table->string('token_id');
            $table->timestamps();

            $table->unique(['asset', 'token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chain_internal_tokens');
    }
};
