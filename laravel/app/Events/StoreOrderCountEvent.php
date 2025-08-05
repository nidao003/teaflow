<?php
/**
 * broadcast(new \App\Events\StoreOrderCountEvent(1));
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */

namespace App\Events;

use App\Models\User;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StoreOrderCountEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $store_id;

    /**
     * Create a new event instance.
     */
    public function __construct($store_id = 0)
    {
        $store_id = $store_id ?: 0;
        $this->store_id = $store_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('store.'.$this->store_id),
        ];
    }

}
