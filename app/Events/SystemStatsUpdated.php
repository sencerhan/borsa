<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SystemStatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /** @var array<string, mixed> */
    public array $stats;

    /** @param array<string, mixed> $stats */
    public function __construct(array $stats)
    {
        $this->stats = $stats;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.stats'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stats.updated';
    }
}
