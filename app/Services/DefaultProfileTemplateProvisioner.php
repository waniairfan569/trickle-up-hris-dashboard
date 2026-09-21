<?php

namespace App\Services;

use App\Models\ProfileField;
use App\Models\ProfileSection;
use App\Models\ProfileTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds the standard "Default Employee Profile" template (sections + fields) for
 * the CURRENT tenant from the shipped blueprint, then assigns it to the tenant's
 * users. Idempotent — a no-op if the tenant already owns the default template.
 *
 * This is what gives every new workspace the same starter profile structure the
 * original workspace has (previously only seeded once, globally).
 */
class DefaultProfileTemplateProvisioner
{
    private const BLUEPRINT = 'database/blueprints/default-profile-template.json';

    /** Create + assign the default template for the current tenant (no-op if present). */
    public function provisionForCurrentTenant(): ?ProfileTemplate
    {
        $blueprint = $this->blueprint();
        if (! $blueprint) {
            return null;
        }

        $slug = $blueprint['template']['slug'] ?? 'default-employee-profile';

        // Already provisioned for this tenant (the global scope keeps this per-tenant).
        if ($existing = ProfileTemplate::where('slug', $slug)->first()) {
            $this->assignToUsers($existing);
            return $existing;
        }

        return DB::transaction(function () use ($blueprint, $slug) {
            $t = $blueprint['template'];
            $template = ProfileTemplate::create([
                'name'        => $t['name'] ?? 'Default Employee Profile',
                'slug'        => $slug,
                'type'        => $t['type'] ?? 'default',
                'description' => $t['description'] ?? null,
                'is_active'   => $t['is_active'] ?? true,
                'sort_order'  => $t['sort_order'] ?? 1,
            ]);

            foreach ($blueprint['sections'] ?? [] as $s) {
                $section = ProfileSection::create([
                    'template_id' => $template->id,
                    'name'        => $s['name'],
                    'slug'        => $s['slug'],
                    'tab'         => $s['tab'] ?? null,
                    'icon'        => $s['icon'] ?? null,
                    'sort_order'  => $s['sort_order'] ?? 0,
                ]);

                foreach ($s['fields'] ?? [] as $f) {
                    ProfileField::create([
                        'section_id'        => $section->id,
                        'name'              => $f['name'],
                        'key'               => $f['key'],
                        'type'              => $f['type'],
                        'options'           => $f['options'] ?? null,
                        'placeholder'       => $f['placeholder'] ?? null,
                        'is_required'       => $f['is_required'] ?? false,
                        'is_system'         => $f['is_system'] ?? false,
                        'is_encrypted'      => $f['is_encrypted'] ?? false,
                        'visibility'        => $f['visibility'] ?? 'internal',
                        'employee_can_edit' => $f['employee_can_edit'] ?? false,
                        'sort_order'        => $f['sort_order'] ?? 0,
                    ]);
                }
            }

            $this->assignToUsers($template);

            return $template;
        });
    }

    /** Attach the template to every current user in the tenant (idempotent). */
    private function assignToUsers(ProfileTemplate $template): void
    {
        $userIds = User::pluck('id');
        if ($userIds->isEmpty()) {
            return;
        }

        $assignedBy = $userIds->first();
        $rows = $userIds->map(fn ($id) => [
            'user_id'     => $id,
            'template_id' => $template->id,
            'assigned_by' => $assignedBy,
            'assigned_at' => now(),
        ])->all();

        DB::table('employee_profile_templates')->insertOrIgnore($rows);
    }

    private function blueprint(): ?array
    {
        $path = base_path(self::BLUEPRINT);
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && ! empty($data['sections']) ? $data : null;
    }
}
