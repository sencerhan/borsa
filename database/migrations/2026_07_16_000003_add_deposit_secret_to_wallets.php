<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            // Deposit adresinin Ed25519 özel anahtarı (şifreli saklanır).
            // Yalnızca borsanın kontrol ettiği zincir cüzdanlarında (ör. BK$) dolu olur;
            // gerçek çekimde bu anahtarla zincir işlemi imzalanır.
            $table->text('deposit_secret')->nullable()->after('deposit_address');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('deposit_secret');
        });
    }
};
