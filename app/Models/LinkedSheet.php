<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LinkedSheet extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'name', 'url', 'description', 'category', 'provider',
        'opens_count', 'last_opened_at', 'created_by',
    ];

    protected $casts = [
        'opens_count' => 'integer',
        'last_opened_at' => 'datetime',
    ];

    // ── Relationships ───────────────────────────────────────────────────────────

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Provider / embed helpers ────────────────────────────────────────────────

    /** Best-guess provider from a URL — used for the icon + preview strategy. */
    public static function detectProvider(string $url): string
    {
        $u = strtolower($url);

        return match (true) {
            str_contains($u, 'docs.google.com/spreadsheets') => 'google',
            str_contains($u, 'sharepoint.com'), str_contains($u, 'officeapps'), str_contains($u, '1drv.ms'),
                str_contains($u, 'onedrive'), str_ends_with($u, '.xlsx'), str_ends_with($u, '.xls') => 'excel',
            str_contains($u, 'airtable.com') => 'airtable',
            default => 'link',
        };
    }

    public function getIsGoogleAttribute(): bool
    {
        return $this->provider === 'google' || str_contains((string) $this->url, 'docs.google.com/spreadsheets');
    }

    /**
     * A URL suitable for embedding in an <iframe>. For Google Sheets we rewrite to
     * the read-only /preview view (embeds for "anyone with the link" sheets).
     * Falls back to the raw URL for other providers (best effort).
     */
    public function getEmbedUrlAttribute(): string
    {
        if ($this->is_google && preg_match('~/spreadsheets/d/([a-zA-Z0-9\-_]+)~', $this->url, $m)) {
            $embed = "https://docs.google.com/spreadsheets/d/{$m[1]}/preview";
            if (preg_match('~[#?&]gid=([0-9]+)~', $this->url, $g)) {
                $embed .= "?gid={$g[1]}";
            }

            return $embed;
        }

        return $this->url;
    }

    public function getProviderLabelAttribute(): string
    {
        return [
            'google'   => 'Google Sheet',
            'excel'    => 'Excel / OneDrive',
            'airtable' => 'Airtable',
            'link'     => 'Link',
        ][$this->provider] ?? 'Link';
    }
}
