<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentSigners;
use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    use BelongsToTenant;
    use HasDocumentSigners;
    use HasFactory;

    protected $fillable = [
        'document_template_id',
        'subject_employee_id',
        'created_by',
        'status',
        'current_position',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'current_position' => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function subject()
    {
        return $this->belongsTo(User::class, 'subject_employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signers()
    {
        return $this->hasMany(DocumentRequestSigner::class)->orderBy('position');
    }

    public function events()
    {
        return $this->hasMany(DocumentRequestEvent::class)->latest('created_at');
    }

    /** The signer whose turn it currently is (next pending in order). */
    /**
     * Is this user the one who must sign right now?
     *
     * Whose turn it is comes from HasDocumentSigners, shared with HR documents,
     * so the rule is written once. The extra condition here is this system's
     * own: a request that is not in progress is waiting on nobody.
     */
    public function isAwaiting(User $user): bool
    {
        return $this->status === 'in_progress' && $this->isAwaitingSignature($user);
    }

    /** Record an audit event. */
    public function logEvent(string $action, ?User $user = null, ?string $detail = null, ?string $ip = null): void
    {
        $this->events()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'detail' => $detail,
            'ip' => $ip,
            'created_at' => now(),
        ]);
    }
}
