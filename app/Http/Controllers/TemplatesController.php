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

        // The queue only exists because of a setting on a profile field, so it
        // belongs next to where that setting is made. The badge is what makes
        // it findable — nobody goes looking for an empty queue.
        $pending = \App\Models\ProfileChangeRequest::pending()->count();

        $tiles[] = [
            'route' => 'profile-changes.index',
            'icon'  => 'user-pen',
            'tone'  => 'amber',
            'title' => 'Record changes',
            'text'  => 'Changes employees have asked to make to their own records, where the field is set to be approved first.',
            'stat'  => $pending,
            'stat_label' => 'waiting',
            'meta'  => $pending > 0 ? 'needs answering' : 'nothing waiting',
            'badge' => $pending > 0 ? $pending : null,
        ];

        return view('templates.index', compact('tiles'));
    }
}
