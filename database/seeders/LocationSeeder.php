<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['name' => 'New York HQ', 'country' => 'USA', 'city' => 'New York'],
            ['name' => 'London Office', 'country' => 'UK', 'city' => 'London'],
            ['name' => 'Remote', 'country' => 'Global', 'city' => 'Anywhere'],
        ];
        
        $id = 1;
        foreach ($locations as $loc) {
            DB::table('locations')->insert([
                'id' => $id++,
                'company_id' => 1,
                'name' => $loc['name'],
                'country' => $loc['country'],
                'city' => $loc['city'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
