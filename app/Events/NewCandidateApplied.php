<?php
namespace App\Events;

use App\Models\Candidate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewCandidateApplied implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $candidate;
    public $companyId;

    public function __construct(Candidate $candidate, $companyId)
    {
        $this->candidate = $candidate;
        $this->companyId = $companyId;
    }

    public function broadcastOn()
    {
        return [new PrivateChannel('company.' . $this->companyId)];
    }

    public function broadcastAs()
    {
        return 'candidate.applied';
    }
}
