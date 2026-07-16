<?php

namespace App\Events;

use App\Models\Market;
use App\Services\MarketDataService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OrderBookUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /** @var array<string, mixed> */
    public array $orderBook;

    /** @var array<string, mixed> */
    public array $ticker;

    public string $symbol;

    public function __construct(Market $market)
    {
        $this->symbol = $market->symbol;

        $marketData = app(MarketDataService::class);
        $this->orderBook = $marketData->orderBook($market);
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
        return 'orderbook.updated';
    }
}
