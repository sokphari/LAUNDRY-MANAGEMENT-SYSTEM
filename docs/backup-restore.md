# Backup and restore

Run the database migration after deploying the Backup & Restore feature:

```sh
php database/migrate.php
```

The Admin module can create a manual backup, control scheduled backups, download backups, and restore a selected backup. Backups are gzip-compressed snapshots of the application tables, including customers, orders and order history, services, payments and refunds, users and permissions, audit logs, and optionally uploaded profile images. Migration history and backup settings are deliberately not restored.

Backup files are written under `storage/backups/`, outside `public/`. The application sets the directory to mode `0700` and the files to mode `0600`. Keep the project storage directory on a protected, persistent disk and include it in the server’s off-site backup plan.

Run the scheduler hourly. Its due check applies the selected daily, six-hour, or weekly frequency. Add a cron entry using the PHP executable and absolute project path for the server:

```cron
0 * * * * /usr/bin/php "/absolute/path/to/Laundry Management System/database/backup-scheduler.php" >> "/absolute/path/to/Laundry Management System/storage/logs/backup-scheduler.log" 2>&1
```

The scheduler checks Admin settings and uses a MySQL lock to avoid overlapping runs. Turning automatic backups off skips future scheduled backups and leaves existing files untouched. Optional cleanup removes only automatic backups older than the 30-day retention period after a successful new backup; manual and pre-restore backups are retained. Failure notifications are recorded in audit logs. Off-site storage is unavailable until a protected remote destination is configured.

Restore requires an Admin session, CSRF validation, a confirmation dialog, and typing `RESTORE`. Before restoring, the app creates a separate safety backup of the current data. Restore replaces application-table data in a database transaction; backup settings remain as currently configured. A restored snapshot must match the live table and column schema, so restore it with a compatible application version.
