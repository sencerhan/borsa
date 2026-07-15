<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buy_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('sell_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('taker_side', 4); // eşleşmeyi tetikleyen taraf: buy | sell
            $table->decimal('price', 36, 18);
            $table->decimal('quantity', 36, 18);      // base miktarı
            $table->decimal('quote_amount', 36, 18);  // price * quantity
            $table->decimal('buyer_fee', 36, 18)->default(0);  // base cinsinden (alıcı base alır)
            $table->decimal('seller_fee', 36, 18)->default(0); // quote cinsinden (satıcı quote alır)
            $table->timestamps();

            $table->index(['market_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
