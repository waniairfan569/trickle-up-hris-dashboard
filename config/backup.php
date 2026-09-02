<?php

/*
 | Self-contained backups (no external package). `backup:run` dumps the
 | database and archives uploaded files, stores them on `disk`, optionally
 | copies them off-site to `upload_disk` (e.g. s3), and prunes old copies.
 */

return [
    // Filesystem disk backups are written to first (see config/filesystems.php).
    'disk' => env('BACKUP_DISK', 'local'),

    // Optional second disk to copy each backup to — set to 's3' for off-site.
    'upload_disk' => env('BACKUP_UPLOAD_DISK'),

    // Path to the mysqldump binary. Default assumes it's on PATH (true on most
    // Linux hosts); set BACKUP_MYSQLDUMP_PATH on Windows/XAMPP.
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    // Delete backups older than this many days on the primary disk.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // Also archive uploaded files under storage/app/public.
    'include_files' => (bool) env('BACKUP_INCLUDE_FILES', true),
];
