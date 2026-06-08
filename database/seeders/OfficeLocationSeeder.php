<?php

namespace Database\Seeders;

use App\Models\OfficeLocation;
use App\Models\User;
use Illuminate\Database\Seeder;

class OfficeLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $londonHQ = OfficeLocation::create([
            'name' => 'London HQ',
            'latitude' => 51.5074000,
            'longitude' => -0.1278000,
            'radius_meters' => 150,
            'allow_remote' => false,
            'is_active' => true
        ]);

        $remote = OfficeLocation::create([
            'name' => 'Remote / Work From Home',
            'latitude' => 51.5074000,
            'longitude' => -0.1278000,
            'radius_meters' => 100,
            'allow_remote' => true,
            'is_active' => true
        ]);

        // Assign to specific users based on their names
        $londonUsers = ['Sara', 'Hamid', 'Dave', 'Ali'];
        
        foreach ($londonUsers as $firstName) {
            $user = User::where('first_name', $firstName)->first();
            if ($user) {
                $user->officeLocations()->syncWithoutDetaching([$londonHQ->id => ['is_primary' => true]]);
            }
        }

        $remoteUser = User::where('first_name', 'Nida')->first();
        if ($remoteUser) {
            $remoteUser->officeLocations()->syncWithoutDetaching([$remote->id => ['is_primary' => true]]);
        }
    }
}
