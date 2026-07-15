<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('network', 40)->nullable()->after('name'); // TRC20, BK Chain vb.
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->string('deposit_address')->nullable()->after('asset'); // simüle yatırma adresi
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('network');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('deposit_address');
        });
    }
};
