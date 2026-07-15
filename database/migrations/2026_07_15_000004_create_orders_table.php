<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->string('side', 4);   // buy | sell
            $table->string('type', 6);   // limit | market
            $table->string('status', 20)->default('open'); // open | partially_filled | filled | cancelled
            $table->decimal('price', 36, 18)->nullable(); // market emirde null
            $table->decimal('quantity', 36, 18);          // base cinsinden hedef miktar (market buy'da 0 olabilir)
            $table->decimal('filled_quantity', 36, 18)->default(0);
            $table->decimal('quote_amount', 36, 18)->nullable(); // market buy: harcanacak quote tutarı
            $table->decimal('quote_filled', 36, 18)->default(0); // gerçekleşen quote hacmi
            $table->string('locked_asset', 20)->nullable();      // kilitlenen varlık
            $table->decimal('locked_amount', 36, 18)->default(0); // hâlâ kilitli kalan miktar
            $table->timestamps();

            // Fiyat-zaman önceliği için sıralama indeksi
            $table->index(['market_id', 'side', 'status', 'price', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
