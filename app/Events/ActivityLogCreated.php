<?php
namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityLogCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $activityLog;
    public $companyId;

    public function __construct(ActivityLog $activityLog, $companyId)
    {
        $this->activityLog = $activityLog;
        $this->companyId = $companyId;
    }

    public function broadcastOn()
    {
        return [new PrivateChannel('company.' . $this->companyId)];
    }

    public function broadcastAs()
    {
        return 'activity.created';
    }
}
