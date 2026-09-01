<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An audit entry for a company's subscription lifecycle — platform-global,
 * visible only to operators.
 */
class SubscriptionEvent extends Model
{
    protected $fillable = ['tenant_id', 'operator_id', 'type', 'description'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** Record a subscription event, stamping the acting operator. */
    public static function record(Tenant $tenant, string $type, string $description): self
    {
        return static::create([
            'tenant_id'   => $tenant->id,
            'operator_id' => optional(auth()->user())->isOperator() ? auth()->id() : null,
            'type'        => $type,
            'description' => $description,
        ]);
    }

    public function getIconAttribute(): string
    {
        return [
            'plan_changed'    => 'arrow-left-right',
            'canceled'        => 'x-circle',
            'reactivated'     => 'refresh-cw',
            'trial_extended'  => 'clock',
            'discount_applied'=> 'badge-percent',
            'suspended'       => 'ban',
            'activated'       => 'check-circle',
        ][$this->type] ?? 'activity';
    }
}
