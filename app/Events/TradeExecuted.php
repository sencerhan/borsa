<?php

namespace App\Events;

use App\Models\Market;
use App\Models\Trade;
use App\Services\MarketDataService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TradeExecuted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /** @var array<string, mixed> */
    public array $trade;

    /** @var array<string, mixed> */
    public array $ticker;

    public string $symbol;

    public function __construct(Trade $trade)
    {
        $market = $trade->market;
        $this->symbol = $market->symbol;

        $this->trade = [
            'id' => $trade->id,
            'price' => $trade->price,
            'quantity' => $trade->quantity,
            'quote_amount' => $trade->quote_amount,
            'taker_side' => $trade->taker_side->value,
            'created_at' => $trade->created_at->toIso8601String(),
        ];

        $marketData = app(MarketDataService::class);
        $this->ticker = $marketData->ticker($market);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('market.'.Market::channelSlug($this->symbol)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trade.executed';
    }
}
