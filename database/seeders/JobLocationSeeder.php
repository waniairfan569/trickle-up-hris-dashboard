<?php

namespace Database\Seeders;

use App\Models\JobLocation;
use App\Models\User;
use Illuminate\Database\Seeder;

class JobLocationSeeder extends Seeder
{
    /**
     * NOTE: not registered in DatabaseSeeder on purpose — the round-robin
     * fallback below assigns every unassigned user a location, which is only
     * appropriate for local/demo data, not a production employee list.
     * Run manually:  php artisan db:seed --class=JobLocationSeeder
     */
    public function run(): void
    {
        $locations = [
            ['name' => 'Lahore HQ',              'city' => 'Lahore',  'country' => 'PK', 'country_name' => 'Pakistan',       'timezone' => 'Asia/Karachi',  'is_remote' => false],
            ['name' => 'Karachi Office',         'city' => 'Karachi', 'country' => 'PK', 'country_name' => 'Pakistan',       'timezone' => 'Asia/Karachi',  'is_remote' => false],
            ['name' => 'London Office',          'city' => 'London',  'country' => 'GB', 'country_name' => 'United Kingdom',  'timezone' => 'Europe/London', 'is_remote' => false],
            ['name' => 'Remote — Pakistan',      'city' => null,      'country' => 'PK', 'country_name' => 'Pakistan',       'timezone' => 'Asia/Karachi',  'is_remote' => true],
            ['name' => 'Remote — International', 'city' => null,      'country' => null, 'country_name' => null,             'timezone' => null,            'is_remote' => true],
        ];

        $created = [];
        foreach ($locations as $loc) {
            $created[$loc['name']] = JobLocation::firstOrCreate(['name' => $loc['name']], $loc);
        }

        // Spec-intended assignments (no-op if these named users don't exist here).
        $byName = [
            'Sara Rahman' => 'Lahore HQ',
            'Hamid Malik' => 'Lahore HQ',
            'Dave Khan'   => 'Remote — Pakistan',
            'Ali Javed'   => 'Lahore HQ',
            'Nida Zahra'  => 'Remote — International',
        ];
        foreach ($byName as $fullName => $locName) {
            [$first, $last] = array_pad(explode(' ', $fullName, 2), 2, '');
            User::where('first_name', $first)->where('last_name', $last)
                ->update(['job_location_id' => $created[$locName]->id]);
        }

        // Demo fallback: assign any still-unassigned user round-robin so the
        // list/counts are populated for viewing.
        $pool = array_values($created);
        $i = 0;
        User::whereNull('job_location_id')->orderBy('id')->get()->each(function (User $u) use ($pool, &$i) {
            $u->update(['job_location_id' => $pool[$i % count($pool)]->id]);
            $i++;
        });

        // Recompute cached employee_count for every location.
        JobLocation::all()->each->refreshEmployeeCount();
    }
}
