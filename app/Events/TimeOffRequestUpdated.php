<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimeOffRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $timeOffRequestId,
        public int $companyId
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.' . $this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'timeoff.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'time_off_request_id' => $this->timeOffRequestId,
            'company_id'          => $this->companyId,
        ];
    }
}
