<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A monthly Lateness Review has no single "Date of lateness", so that box came
 * out blank while the table below listed every date. Point the field at the
 * new lateness_dates prefill so it summarises them.
 *
 * Only fills fields that have no prefill of their own — a template someone has
 * already configured is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_document_templates')) {
            return;
        }

        foreach (DB::table('hr_document_templates')->whereNull('deleted_at')->get(['id', 'schema']) as $row) {
            $schema = json_decode((string) $row->schema, true);
            if (! is_array($schema)) {
                continue;
            }

            $changed = false;
            foreach ($schema as $s => $section) {
                foreach ($section['fields'] ?? [] as $f => $field) {
                    $id = strtolower((string) ($field['id'] ?? ''));
                    $label = strtolower((string) ($field['label'] ?? ''));
                    $isLatenessDate = $id === 'date_of_lateness'
                        || (str_contains($label, 'date') && str_contains($label, 'lateness'));

                    if ($isLatenessDate && empty($field['prefill'])) {
                        $schema[$s]['fields'][$f]['prefill'] = 'lateness_dates';
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

    public function down(): void
    {
        // Leaves the prefill in place; removing it would only reintroduce the blank.
    }
};
