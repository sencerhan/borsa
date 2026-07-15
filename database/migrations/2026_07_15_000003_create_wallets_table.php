<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 20); // BK$, USDT
            $table->decimal('available', 36, 18)->default(0); // serbest bakiye
            $table->decimal('locked', 36, 18)->default(0);    // açık emirlerde kilitli
            $table->timestamps();

            $table->unique(['user_id', 'asset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
