<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 20);
            $table->string('type', 20); // deposit|withdraw|trade|fee|lock|unlock|refund
            $table->decimal('amount', 36, 18);          // işaretli değişim (available üzerinde)
            $table->decimal('available_after', 36, 18); // işlem sonrası serbest bakiye
            $table->decimal('locked_after', 36, 18);    // işlem sonrası kilitli bakiye
            $table->nullableMorphs('reference');        // deposit/withdraw/trade/order kaydı
            $table->timestamps();

            $table->index(['user_id', 'asset', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
