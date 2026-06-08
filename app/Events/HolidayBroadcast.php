<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HolidayBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $holidayName;

    /**
     * Create a new event instance.
     */
    public function __construct($holidayName, $message = null)
    {
        $this->holidayName = $holidayName;
        $this->message = $message ?: "A public holiday '{$holidayName}' has been announced. Enjoy your time off!";
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('hr-portal'),
        ];
    }

    public function broadcastAs()
    {
        return 'holiday.broadcast';
    }
}
