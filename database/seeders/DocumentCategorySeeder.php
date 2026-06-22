<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'HR Policies', 'icon' => 'file-text', 'color' => '#3B82F6', 'sort_order' => 1],
            ['name' => 'IT Guidelines', 'icon' => 'laptop', 'color' => '#8B5CF6', 'sort_order' => 2],
            ['name' => 'Finance', 'icon' => 'coins', 'color' => '#10B981', 'sort_order' => 3],
            ['name' => 'Legal', 'icon' => 'scale', 'color' => '#EF4444', 'sort_order' => 4],
            ['name' => 'Onboarding', 'icon' => 'rocket', 'color' => '#F59E0B', 'sort_order' => 5],
            ['name' => 'General', 'icon' => 'folder', 'color' => '#6B7280', 'sort_order' => 6],
        ];

        foreach ($categories as $c) {
            DocumentCategory::firstOrCreate(
                ['slug' => Str::slug($c['name'])],
                $c
            );
        }
    }
}
