<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PublicHoliday;
use App\Models\Company;

class PublicHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found, skipping holiday seeder.');
            return;
        }

        $year = 2026;
        $holidays = [
            'US' => [
                ['name' => "New Year's Day",         'date' => '2026-01-01'],
                ['name' => 'Martin Luther King Day',  'date' => '2026-01-19'],
                ['name' => "Presidents' Day",         'date' => '2026-02-16'],
                ['name' => 'Memorial Day',            'date' => '2026-05-25'],
                ['name' => 'Independence Day',        'date' => '2026-07-04'],
                ['name' => 'Labor Day',               'date' => '2026-09-07'],
                ['name' => 'Columbus Day',            'date' => '2026-10-12'],
                ['name' => 'Veterans Day',            'date' => '2026-11-11'],
                ['name' => 'Thanksgiving Day',        'date' => '2026-11-26'],
                ['name' => 'Christmas Day',           'date' => '2026-12-25'],
            ],
            'UK' => [
                ['name' => "New Year's Day",          'date' => '2026-01-01'],
                ['name' => 'Good Friday',             'date' => '2026-04-03'],
                ['name' => 'Easter Monday',           'date' => '2026-04-06'],
                ['name' => 'Early May Bank Holiday',  'date' => '2026-05-04'],
                ['name' => 'Spring Bank Holiday',     'date' => '2026-05-25'],
                ['name' => 'Summer Bank Holiday',     'date' => '2026-08-31'],
                ['name' => 'Christmas Day',           'date' => '2026-12-25'],
                ['name' => 'Boxing Day',              'date' => '2026-12-26'],
            ],
            'PK' => [
                ['name' => 'Kashmir Day',             'date' => '2026-02-05'],
                ['name' => 'Pakistan Day',            'date' => '2026-03-23'],
                ['name' => 'Labour Day',              'date' => '2026-05-01'],
                ['name' => 'Independence Day',        'date' => '2026-08-14'],
                ['name' => "Quaid-e-Azam's Birthday", 'date' => '2026-12-25'],
                ['name' => 'Eid ul Fitr (approx)',    'date' => '2026-03-30'],
                ['name' => 'Eid ul Adha (approx)',    'date' => '2026-06-07'],
            ],
            'IN' => [
                ['name' => "New Year's Day",          'date' => '2026-01-01'],
                ['name' => 'Republic Day',            'date' => '2026-01-26'],
                ['name' => 'Holi',                    'date' => '2026-03-21'],
                ['name' => 'Good Friday',             'date' => '2026-04-03'],
                ['name' => 'Ambedkar Jayanti',        'date' => '2026-04-14'],
                ['name' => 'Labour Day',              'date' => '2026-05-01'],
                ['name' => 'Independence Day',        'date' => '2026-08-15'],
                ['name' => 'Gandhi Jayanti',          'date' => '2026-10-02'],
                ['name' => 'Diwali',                  'date' => '2026-10-27'],
                ['name' => 'Christmas Day',           'date' => '2026-12-25'],
            ],
        ];

        foreach ($holidays as $country => $list) {
            foreach ($list as $h) {
                PublicHoliday::updateOrCreate(
                    ['company_id' => $company->id, 'date' => $h['date'], 'country_code' => $country],
                    [
                        'name'    => $h['name'],
                        'year'    => $year,
                        'type'    => 'national',
                        'is_optional' => false,
                    ]
                );
            }
        }

        $this->command->info('Public holidays seeded for US, UK, PK, IN — 2026.');
    }
}
