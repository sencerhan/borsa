<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            // Zincir işlem kimliği (txid): yatırmanın hangi on-chain transferden geldiği.
            if (! Schema::hasColumn('deposits', 'txid')) {
                $table->string('txid')->nullable()->after('reference');
            }

            // Aynı zincir transferi iki kez kredilenmesin (idempotent yatırma doğrulama).
            $table->unique(['asset', 'txid']);
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropUnique(['asset', 'txid']);
        });
    }
};
