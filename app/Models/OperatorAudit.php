<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platform-level audit of sensitive operator actions (owner-visible only). */
class OperatorAudit extends Model
{
    protected $fillable = ['operator_id', 'action', 'description', 'target_tenant_id', 'ip'];

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public static function record(string $action, string $description, ?int $targetTenantId = null): self
    {
        return static::create([
            'operator_id'      => auth()->id(),
            'action'           => $action,
            'description'      => $description,
            'target_tenant_id' => $targetTenantId,
            'ip'               => request()->ip(),
        ]);
    }

    public function getIconAttribute(): string
    {
        return [
            'impersonate'           => 'log-in',
            'operator_added'        => 'user-plus',
            'operator_role_changed' => 'shield',
            'operator_revoked'      => 'user-minus',
        ][$this->action] ?? 'activity';
    }
}
