<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    use BelongsToTenant;
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
    public function currentSigner()
    {
        return $this->signers->firstWhere('status', 'pending');
    }

    /** Is this user the one who must sign right now? */
    public function isAwaiting(User $user): bool
    {
        $current = $this->currentSigner();

        return $this->status === 'in_progress' && $current && (int) $current->user_id === (int) $user->id;
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
