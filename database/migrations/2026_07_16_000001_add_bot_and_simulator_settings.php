<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->boolean('bot_active')->default(false);
            $table->decimal('bot_reference_price', 36, 18)->default('0.030000');
            $table->decimal('bot_volatility', 8, 6)->default('0.005000'); // %0.5
            $table->decimal('bot_spread', 8, 6)->default('0.010000');     // %1.0
            $table->decimal('bot_min_qty', 36, 18)->default('10.000000');
            $table->decimal('bot_max_qty', 36, 18)->default('1000.000000');
            $table->unsignedInteger('bot_interval')->default(10); // saniye

            // Global Yatırma/Çekme Simülatör Ayarları (Bu market bazında da yönetilebilir veya genel)
            $table->boolean('simulator_active')->default(false);
            $table->unsignedInteger('simulator_interval')->default(30); // saniye
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->string('txid', 100)->nullable()->after('reference');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('txid', 100)->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropColumn([
                'bot_active',
                'bot_reference_price',
                'bot_volatility',
                'bot_spread',
                'bot_min_qty',
                'bot_max_qty',
                'bot_interval',
                'simulator_active',
                'simulator_interval',
            ]);
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn('txid');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('txid');
        });
    }
};
