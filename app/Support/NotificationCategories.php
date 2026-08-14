<?php

namespace App\Support;

/**
 * Maps every employee-facing notification to a category the employee can toggle
 * in Settings. Admin-only notifications (approvals, requests-to-review, etc.)
 * are intentionally absent, so they are never gated and always send.
 *
 * One source of truth for both the Settings UI and the NotificationSending gate.
 */
class NotificationCategories
{
    /** Notification class basename => category key. */
    public const MAP = [
        'TimeOffRequestStatusChanged'          => 'time_off',
        'LeaveRenewalNotification'             => 'time_off',
        'LeaveCategoryChanged'                 => 'time_off',
        'DocumentSignatureRequested'           => 'documents',
        'DocumentSignatureReminder'            => 'documents',
        'DocumentSigningCompleted'             => 'documents',
        'FormAssigned'                         => 'forms',
        'FormReviewed'                         => 'forms',
        'FormReviewAccessGranted'              => 'forms',
        'PolicyAssigned'                       => 'policies',
        'EquipmentRequestReviewedNotification' => 'equipment',
        'CodeProvidedNotification'             => 'code_requests',
        'CodeRejectedNotification'             => 'code_requests',
        'PayReviewRecorded'                    => 'pay_performance',
        'ProbationCompleted'                   => 'pay_performance',
        'LateArrivalsWarningNotification'      => 'attendance',
        'LatenessDeductionNotification'        => 'attendance',
        'TimeTrackingReminder'                 => 'attendance',
        'AnnouncementPosted'                   => 'announcements',
        'EventPublishedNotification'           => 'events',
        'FeedbackRespondedNotification'        => 'feedback',
    ];

    /** Category key => [label, description, icon]. Order = display order. */
    public const CATEGORIES = [
        'time_off'        => ['Time off & leave', 'Approvals, rejections, renewals and balance changes.', 'palmtree'],
        'documents'       => ['Documents to sign', 'Documents sent to you and signing reminders.', 'file-signature'],
        'policies'        => ['Policies', 'New company policies assigned for you to acknowledge.', 'book-text'],
        'forms'           => ['Forms', 'Forms assigned to you and review outcomes.', 'clipboard-list'],
        'equipment'       => ['Equipment', 'Decisions on your take-equipment-home requests.', 'package'],
        'code_requests'   => ['Login-code requests', 'When a login code you asked for is shared or declined.', 'key-round'],
        'pay_performance' => ['Pay & performance', 'Pay reviews and probation outcomes.', 'badge-dollar-sign'],
        'attendance'      => ['Attendance', 'Clock-in reminders and lateness warnings.', 'clock'],
        'announcements'   => ['Announcements', 'Company-wide announcements from your team.', 'megaphone'],
        'events'          => ['Company events', 'New events published to your company calendar.', 'calendar-days'],
        'feedback'        => ['Feedback replies', 'Replies from HR to feedback or issues you raised.', 'message-square-reply'],
    ];

    /** Categories whose EMAIL channel can't be disabled (compliance / records). */
    public const MANDATORY_EMAIL = ['documents', 'pay_performance'];

    /** Resolve the category for a notification instance (or null if not gated). */
    public static function for(object $notification): ?string
    {
        $base = class_basename($notification);

        return self::MAP[$base] ?? null;
    }
}
