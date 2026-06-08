<?php
namespace App\Events;

use App\Models\Candidate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CandidateStageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $candidate;

    public function __construct(Candidate $candidate)
    {
        $this->candidate = $candidate;
        $this->candidate->loadMissing('job');
    }

    public function broadcastOn()
    {
        return [new PrivateChannel('company.' . $this->candidate->job->company_id)];
    }

    public function broadcastAs()
    {
        return 'candidate.stage.updated';
    }
}
