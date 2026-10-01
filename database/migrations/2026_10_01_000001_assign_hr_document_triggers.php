<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-drafting is now driven solely by a template's assigned event (the
 * "Auto-drafts for" picker on the Documents page); the generator no longer
 * guesses from the template name.
 *
 * Templates created before the picker existed may carry no assignment — a
 * custom "Work from Home" or "Unplanned Leave (hourly)" form never had one to
 * set. Fill those in from the name so nothing stops auto-drafting on upgrade.
 * Only blanks are touched: an assignment already made is left alone.
 */
return new class extends Migration
{
    /** name fragment => event, most specific first (a name matches at most one). */
    private const BY_NAME = [
        'lateness'       => 'lateness',
        'hourly'         => 'hourly',
        'work from home' => 'wfh',
        'wfh'            => 'wfh',
        'return to work' => 'absence',
        'return-to-work' => 'absence',
        'absence'        => 'absence',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('hr_document_templates')) {
            return;
        }

        $templates = DB::table('hr_document_templates')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('prefill')->orWhere('prefill', ''))
            ->get(['id', 'name', 'tenant_id']);

        // One template per event per workspace — don't hand an event to a second
        // template when one already holds it.
        $taken = DB::table('hr_document_templates')
            ->whereNull('deleted_at')
            ->whereNotNull('prefill')->where('prefill', '!=', '')
            ->get(['tenant_id', 'prefill'])
            ->map(fn ($r) => $r->tenant_id . '|' . $r->prefill)
            ->flip();

        foreach ($templates as $template) {
            $name = strtolower((string) $template->name);

            foreach (self::BY_NAME as $fragment => $trigger) {
                if (! str_contains($name, $fragment)) {
                    continue;
                }

                $key = $template->tenant_id . '|' . $trigger;
                if ($taken->has($key)) {
                    break;
                }

                DB::table('hr_document_templates')->where('id', $template->id)->update(['prefill' => $trigger]);
                $taken[$key] = true;
                break;
            }
        }
    }

    public function down(): void
    {
        // Assignments are user-visible configuration — keep them.
    }
};
