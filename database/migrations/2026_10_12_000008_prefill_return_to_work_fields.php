<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Return to Work Form is drafted per leave, but its boxes for the dates and
 * the account of the absence had no prefill, so they came out blank and the
 * note the employee wrote from their portal never reached the document.
 *
 * Point those boxes at the leave they are about. Only fills fields that have no
 * prefill of their own — a template someone has already configured is left alone.
 */
return new class extends Migration
{
    /** label fragments that must all appear => the prefill token to set */
    private const RULES = [
        ['words' => ['date', 'absence'], 'token' => 'leave_start_date'],
        ['words' => ['date', 'return'],  'token' => 'leave_return_date'],
        ['words' => ['further', 'details'], 'token' => 'leave_return_note'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('hr_document_templates')) {
            return;
        }

        $rows = DB::table('hr_document_templates')
            ->whereNull('deleted_at')
            ->whereIn('prefill', ['absence', 'hourly'])
            ->get(['id', 'schema']);

        foreach ($rows as $row) {
            $schema = json_decode((string) $row->schema, true);
            if (! is_array($schema)) {
                continue;
            }

            $changed = false;
            foreach ($schema as $s => $section) {
                foreach ($section['fields'] ?? [] as $f => $field) {
                    if (! empty($field['prefill'])) {
                        continue;
                    }

                    $label = strtolower((string) ($field['label'] ?? ''));
                    if ($label === '') {
                        continue;
                    }

                    foreach (self::RULES as $rule) {
                        $matches = array_filter(
                            $rule['words'],
                            fn ($w) => str_contains($label, $w)
                        );

                        if (count($matches) === count($rule['words'])) {
                            $schema[$s]['fields'][$f]['prefill'] = $rule['token'];
                            $changed = true;
                            break;
                        }
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
        // Leaves the prefills in place; removing them would only reintroduce the blanks.
    }
};
