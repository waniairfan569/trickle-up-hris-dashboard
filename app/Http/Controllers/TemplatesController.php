<?php

namespace App\Http\Controllers;

use App\Models\LetterheadTemplate;
use App\Models\ProfileTemplate;
use App\Models\SignatureTemplate;

/**
 * Templates hub — one landing page with a tile for Profile Templates and one for
 * Signature Templates, so the sidebar carries a single "Templates" link instead
 * of a dropdown. Admin-only, same as the pages it links to.
 */
class TemplatesController extends Controller
{
    public function index()
    {
        $tiles = [
            [
                'route' => 'profile-templates.index',
                'icon' => 'user-cog',
                'tone' => 'brand',
                'title' => 'Profile Templates',
                'text' => 'Define the sections and fields that make up an employee profile.',
                'stat' => ProfileTemplate::active()->count(),
                'stat_label' => 'active templates',
                'meta' => ProfileTemplate::whereHas('employees')->count() . ' in use',
            ],
            // Signature blocks and letterheads moved to the Documents hub,
            // with the documents they are applied to.
        ];

        return view('templates.index', compact('tiles'));
    }
}
