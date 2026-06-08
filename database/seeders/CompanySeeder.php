<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'Acme Corp',
            'timezone' => 'America/New_York',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
