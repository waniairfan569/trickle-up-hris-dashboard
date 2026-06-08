<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ProfileTemplate;

return new class extends Migration
{
    public function up(): void
    {
        $descriptions = [
            'Default Employee Profile'  => 'Core personal and employment information — used across all employee profiles.',
            'Emergency contact'         => 'Emergency contact details including name, relationship, and phone numbers to reach in case of urgent situations.',
            'Bank & payroll details'    => 'Payroll and bank account information for salary payments, including account number, sort code, and preferred payment method.',
            'Engineering details'       => 'Technical skills, programming languages, GitHub profile, and engineering-specific information for the engineering department.',
            'Visa & right to work'      => 'Visa type, passport details, right-to-work verification documents, and expiry dates for compliance tracking.',
        ];

        foreach ($descriptions as $name => $description) {
            ProfileTemplate::where('name', $name)
                ->whereNull('description')
                ->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        ProfileTemplate::whereIn('name', [
            'Default Employee Profile',
            'Emergency contact',
            'Bank & payroll details',
            'Engineering details',
            'Visa & right to work',
        ])->update(['description' => null]);
    }
};
