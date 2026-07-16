<?php

namespace App\Events;

use App\Models\Wallet;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WalletUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $userId;

    public string $asset;

    public string $available;

    public string $locked;

    public function __construct(Wallet $wallet)
    {
        $this->userId = $wallet->user_id;
        $this->asset = $wallet->asset;
        $this->available = $wallet->available;
        $this->locked = $wallet->locked;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'wallet.updated';
    }
}
