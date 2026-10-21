<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who signs a document was decided by looking for the word "manager" in the
 * signature field's label. An agency that calls the slot "Line Supervisor",
 * "Head of Department" or writes it in their own language got the wrong
 * answer — silently, with the employee asked to sign the company's section.
 *
 * Give every signature field an explicit `signer_role` instead. This migration
 * only writes what the old rule already decided, so behaviour is identical the
 * moment it runs; from then on it is a setting rather than a guess.
 *
 * Documents already issued keep their own copy of the schema and are not
 * touched — editing a template never changes a document that has gone out.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_document_templates')) {
            return;
        }

        foreach (DB::table('hr_document_templates')->get(['id', 'schema']) as $row) {
            $schema = json_decode((string) $row->schema, true);
            if (! is_array($schema)) {
                continue;
            }

            $changed = false;
            foreach ($schema as $s => $section) {
                foreach ($section['fields'] ?? [] as $f => $field) {
                    if (($field['type'] ?? '') !== 'signature') {
                        continue;
                    }
                    if (! empty($field['signer_role'])) {
                        continue;   // already set — leave it alone
                    }

                    // Exactly the rule the send flow used until now.
                    $isManager = str_contains(mb_strtolower((string) ($field['label'] ?? '')), 'manager');

                    $schema[$s]['fields'][$f]['signer_role'] = $isManager ? 'manager' : 'employee';
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('hr_document_templates')->where('id', $row->id)
                    ->update(['schema' => json_encode($schema)]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hr_document_templates')) {
            return;
        }

        foreach (DB::table('hr_document_templates')->get(['id', 'schema']) as $row) {
            $schema = json_decode((string) $row->schema, true);
            if (! is_array($schema)) {
                continue;
            }

            $changed = false;
            foreach ($schema as $s => $section) {
                foreach ($section['fields'] ?? [] as $f => $field) {
                    if (($field['type'] ?? '') === 'signature' && array_key_exists('signer_role', $field)) {
                        unset($schema[$s]['fields'][$f]['signer_role']);
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                DB::table('hr_document_templates')->where('id', $row->id)
                    ->update(['schema' => json_encode($schema)]);
            }
        }
    }
};
