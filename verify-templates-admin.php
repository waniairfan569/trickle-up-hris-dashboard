<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\ProfileTemplate;
use App\Models\ProfileSection;
use App\Models\ProfileField;
use Illuminate\Support\Str;

try {
    DB::beginTransaction();

    $admin = User::where('email', 'admin@company.com')->first();
    // Fallback to any user if needed
    if (!$admin) {
        $admin = User::first();
    }

    if (!$admin) {
        throw new \Exception("Required admin user not found!");
    }

    echo "========================================================\n";
    echo "  HRIS PROFILE TEMPLATE ADMIN VIEWS VERIFICATION RUNNER \n";
    echo "========================================================\n\n";

    function renderBlade($viewName, $data, $user) {
        try {
            \Illuminate\Support\Facades\Auth::setUser($user);
            // Share errors variable to mimic Laravel environment
            $errors = new \Illuminate\Support\MessageBag();
            \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());
            
            $html = view($viewName, $data)->render();
            echo "✓ $viewName rendered successfully! (" . strlen($html) . " bytes)\n";
        } catch (\Throwable $e) {
            echo "X Error rendering $viewName: " . $e->getMessage() . "\n";
            echo "In file: " . $e->getFile() . " on line " . $e->getLine() . "\n";
        }
    }

    // 1. Let's create a dynamic template to test complete rendering
    $dynamicTemplate = ProfileTemplate::create([
        'name' => 'Verify Test Dynamic Template',
        'slug' => 'verify-test-dynamic-template',
        'type' => 'dynamic',
        'description' => 'A dynamic profile template created for verifying view compilation.',
        'is_active' => true,
    ]);

    // Create a dynamic section under it
    $section = ProfileSection::create([
        'template_id' => $dynamicTemplate->id,
        'name' => 'Verify Test Section',
        'slug' => 'verify-test-section',
        'icon' => 'shield',
        'sort_order' => 1
    ]);

    // Create dynamic fields under that section
    ProfileField::create([
        'section_id' => $section->id,
        'name' => 'Verify Test Text Field',
        'key' => 'verify_test_text_field',
        'type' => 'text',
        'is_required' => true,
        'is_system' => false,
        'is_encrypted' => true,
        'visibility' => 'public',
        'employee_can_edit' => true,
        'sort_order' => 1
    ]);

    ProfileField::create([
        'section_id' => $section->id,
        'name' => 'Verify Test Dropdown Field',
        'key' => 'verify_test_dropdown_field',
        'type' => 'dropdown',
        'options' => ['Option A', 'Option B', 'Option C'],
        'is_required' => false,
        'is_system' => false,
        'is_encrypted' => false,
        'visibility' => 'internal',
        'employee_can_edit' => false,
        'sort_order' => 2
    ]);

    ProfileField::create([
        'section_id' => $section->id,
        'name' => 'Verify System Field Label',
        'key' => 'verify_system_field',
        'type' => 'number',
        'is_required' => true,
        'is_system' => true, // locked
        'is_encrypted' => false,
        'visibility' => 'private',
        'employee_can_edit' => true,
        'sort_order' => 3
    ]);

    // Query template index models
    $templates = ProfileTemplate::withCount(['sections', 'sections as fields_count' => function ($query) {
        $query->join('profile_fields', 'profile_sections.id', '=', 'profile_fields.section_id');
    }])->get();

    // 2. Test Index View
    echo "1. Testing rendering 'profile-templates.index':\n";
    renderBlade('profile-templates.index', compact('templates'), $admin);

    // 3. Test Create View
    echo "\n2. Testing rendering 'profile-templates.create':\n";
    renderBlade('profile-templates.create', [], $admin);

    // 4. Test Edit View (dynamic template)
    echo "\n3. Testing rendering 'profile-templates.edit':\n";
    renderBlade('profile-templates.edit', ['profile_template' => $dynamicTemplate], $admin);

    // 5. Test Show View for Dynamic Template
    $dynamicTemplate->load('sections.fields', 'employees');
    echo "\n4. Testing rendering 'profile-templates.show' (Dynamic Template):\n";
    renderBlade('profile-templates.show', ['profile_template' => $dynamicTemplate], $admin);

    // 6. Test Show View for Default Template
    $defaultTemplate = ProfileTemplate::where('type', 'default')->first();
    if ($defaultTemplate) {
        $defaultTemplate->load('sections.fields', 'employees');
        echo "\n5. Testing rendering 'profile-templates.show' (Default Template):\n";
        renderBlade('profile-templates.show', ['profile_template' => $defaultTemplate], $admin);
    } else {
        echo "\n[INFO] Skipping default template show test (no default template in DB).\n";
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "In file: " . $e->getFile() . " on line " . $e->getLine() . "\n";
} finally {
    DB::rollBack();
    echo "\nTransaction rolled back. Database remains completely clean.\n";
}
