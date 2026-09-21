# Backup & Restore Runbook

Backups are self-contained (no external package). A daily job dumps the database
and archives uploaded files, stores them on the backup disk, copies them off-site
when configured, and prunes old copies.

## What runs, and where backups live
- **Command:** `php artisan backup:run` (scheduled daily ~01:30 — see `routes/console.php`).
- **Output:** on the disk in `config('backup.disk')`, under `backups/`:
  - `backups/db-YYYY-MM-DD_His.sql.gz` — gzipped MySQL dump
  - `backups/files-YYYY-MM-DD_His.zip` — `storage/app/public` uploads
- **Off-site:** if `BACKUP_UPLOAD_DISK` is set (e.g. `s3`), each file is also copied there.
- **Retention:** `BACKUP_KEEP_DAYS` (default 14) on the primary disk.

Relevant env: `BACKUP_DISK`, `BACKUP_UPLOAD_DISK`, `BACKUP_MYSQLDUMP_PATH`,
`BACKUP_MYSQL_PATH`, `BACKUP_KEEP_DAYS`, `BACKUP_INCLUDE_FILES`.

## Restore

> **Destructive** — restoring overwrites the current database. In production the
> command refuses to run without `--force`.

1. **List available backups:**
   ```bash
   php artisan backup:restore --list
   ```
2. **Restore the newest DB backup** (or a specific one with `--file=`):
   ```bash
   php artisan backup:restore                       # newest db-*.sql.gz
   php artisan backup:restore --file=backups/db-2026-09-21_013000.sql.gz
   ```
3. **Also restore uploaded files** from the matching `files-*.zip`:
   ```bash
   php artisan backup:restore --with-files
   ```
4. **In production** add `--force` (and ideally put the app in maintenance mode first):
   ```bash
   php artisan down
   php artisan backup:restore --with-files --force
   php artisan optimize:clear
   php artisan up
   ```
5. Restoring from the **off-site** copy: pass `--disk=s3` (or your upload disk name).

The mysql client path can be overridden with `BACKUP_MYSQL_PATH` (default `mysql`,
which must be on PATH — on Windows/XAMPP set the full path).

## Tested-restore drill (do this monthly)
An untested backup is not a backup. Once a month, prove a restore works against a
throwaway database:

1. Create a scratch database, e.g. `hris_restore_test`.
2. Point a temporary env at it (`DB_DATABASE=hris_restore_test`) — **never** the
   live DB.
3. Run `php artisan backup:restore --list` then `backup:restore --file=<latest>`.
4. `php artisan migrate:status` and spot-check a few tables / row counts.
5. Record the date + result (who ran it, backup file used, outcome). Drop the
   scratch database afterwards.

If a drill fails, treat it as a Sev-1: fix the backup pipeline before you need it.
