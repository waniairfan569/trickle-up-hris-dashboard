<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Event;

/**
 * Communication hub — one landing page with a tile for Announcements and one for
 * Events, so the sidebar carries a single "Communication" link instead of two
 * dropdowns. Admin-only, same as the pages it links to.
 */
class CommunicationController extends Controller
{
    public function index()
    {
        $liveAnnouncements = Announcement::active()->count();
        $pinnedAnnouncements = Announcement::active()->where('is_pinned', true)->count();

        $upcomingEvents = Event::active()->published()
            ->whereDate('date', '>=', today())
            ->count();
        $draftEvents = Event::active()->where('is_published', false)->count();

        $tiles = [
            [
                'route' => 'announcements.index',
                'icon' => 'megaphone',
                'tone' => 'brand',
                'title' => 'Announcements',
                'text' => 'Post company-wide notices, pin the important ones, set expiry dates.',
                'stat' => $liveAnnouncements,
                'stat_label' => 'live now',
                'meta' => $pinnedAnnouncements . ' pinned',
            ],
            [
                'route' => 'events.index',
                'icon' => 'calendar-heart',
                'tone' => 'violet',
                'title' => 'Events',
                'text' => 'Company events and celebrations — publish to everyone, a department or specific people.',
                'stat' => $upcomingEvents,
                'stat_label' => 'upcoming',
                'meta' => $draftEvents . ' unpublished',
            ],
        ];

        return view('communication.index', compact('tiles'));
    }
}
