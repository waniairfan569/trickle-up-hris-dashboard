<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Support\Facades\DB;
use App\Models\User;

try {
    DB::beginTransaction();

    $admin = User::where('email', 'admin@company.com')->first();
    // Fallback to any user if needed
    if (!$admin) {
        $admin = User::first();
    }
    
    // Find manager or employee
    $employee = User::whereHas('roles', fn($q) => $q->where('slug', 'employee'))->first() ?? User::where('id', '!=', $admin->id)->first() ?? $admin;

    if (!$admin || !$employee) {
        throw new \Exception("Required seeded users not found!");
    }

    echo "========================================================\n";
    echo "    HRIS DYNAMIC PROFILE BLADE VIEWS VERIFICATION      \n";
    echo "========================================================\n\n";

    function renderBlade($viewName, $data, $user) {
        try {
            \Illuminate\Support\Facades\Auth::setUser($user);
            $html = view($viewName, $data)->render();
            echo "✓ $viewName rendered successfully! (" . strlen($html) . " bytes)\n";
        } catch (\Throwable $e) {
            echo "X Error rendering $viewName: " . $e->getMessage() . "\n";
            echo "In file: " . $e->getFile() . " on line " . $e->getLine() . "\n";
        }
    }

    // Assign templates if they are not assigned to employee
    $defaultTemplate = \App\Models\ProfileTemplate::where('slug', 'default-employee-profile')->first();
    if ($defaultTemplate && !$employee->profileTemplates()->where('template_id', $defaultTemplate->id)->exists()) {
        $employee->profileTemplates()->attach($defaultTemplate->id, ['assigned_by' => $admin->id, 'assigned_at' => now()]);
    }

    // Let's load the templates for the employee
    $templates = $employee->profileTemplates()->with('sections.fields')->get();

    // Filter fields by visibility for $admin
    $adminTemplates = clone $templates;
    foreach ($adminTemplates as $template) {
        foreach ($template->sections as $section) {
            $section->setRelation('fields', $section->fields->filter(function ($field) use ($admin, $employee) {
                return $field->isVisibleTo($admin, $employee);
            }));
        }
    }

    // Filter fields by visibility for $employee
    $empTemplates = clone $templates;
    foreach ($empTemplates as $template) {
        foreach ($template->sections as $section) {
            $section->setRelation('fields', $section->fields->filter(function ($field) use ($employee) {
                return $field->isVisibleTo($employee, $employee);
            }));
        }
    }

    echo "Testing rendering show view as Admin:\n";
    renderBlade('employees.profile.show', [
        'employee' => $employee,
        'templates' => $adminTemplates,
        'canEdit' => true,
        'editing' => false
    ], $admin);

    echo "\nTesting rendering edit view as Admin:\n";
    renderBlade('employees.profile.show', [
        'employee' => $employee,
        'templates' => $adminTemplates,
        'canEdit' => true,
        'editing' => true
    ], $admin);

    echo "\nTesting rendering show view as Employee:\n";
    renderBlade('employees.profile.show', [
        'employee' => $employee,
        'templates' => $empTemplates,
        'canEdit' => true,
        'editing' => false
    ], $employee);

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
}
