<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 40)->unique(); // BK$/USDT
            $table->string('base_asset', 20);        // işlem gören varlık (BK$)
            $table->string('quote_asset', 20);       // fiyatlama varlığı (USDT)
            $table->decimal('maker_fee', 8, 6)->default(0.001000); // %0.1
            $table->decimal('taker_fee', 8, 6)->default(0.002000); // %0.2
            $table->decimal('min_order_size', 36, 18)->default(0);
            $table->unsignedTinyInteger('price_scale')->default(8);
            $table->unsignedTinyInteger('size_scale')->default(8);
            $table->decimal('last_price', 36, 18)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
