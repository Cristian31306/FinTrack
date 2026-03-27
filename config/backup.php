<?php

return [

    'backup' => [

        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => env('APP_NAME', 'FinTrack'),

        'source' => [

            'files' => [

                /*
                 * The list of directories and files that will be included in the backup.
                 */
                'include' => [],

                /*
                 * These directories will be excluded from the backup.
                 */
                'exclude' => [],

                /*
                 * Determines if symbols links should be followed.
                 */
                'follow_links' => false,

                /*
                 * Determines if it should ignore unreadable files.
                 */
                'ignore_unreadable_directories' => false,

                /*
                 * This method is called to decide if a file should be included in the backup.
                 */
                'relative_path' => null,
            ],

            /*
             * The names of the connections to the databases that should be backed up.
             * MySQL, PostgreSQL, SQLite and Mongo databases are supported.
             *
             * The 'db_dump' section below can be used for configuration of DB dumps.
             */
            'databases' => [
                'mysql',
            ],
        ],

        /*
         * The database dump can be customized here. If not specified, default values are used.
         *
         * You can add extra arguments to the dump command by adding them to the 'add_extra_args'
         * section. For example, for mysql:
         * 'add_extra_args' => '--skip-add-drop-table --no-data'
         */
        'database_dump_file_extension' => 'sql',

        'destination' => [

            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => '',

            /*
             * The disk names on which the backups will be stored.
             */
            'disks' => [
                'google',
            ],
        ],

        /*
         * Temporary directory used for creating the backup zip.
         */
        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * Password used for encrypting the backup zip.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * Compression level used for the backup zip.
         */
        'compression_level' => 9,
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail' and 'slack'.
     * For Slack you need to install guzzlehttp/guzzle and configure a webhook in the Slack settings.
     */
    'notifications' => [

        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent. The default
         * notifiable will use the variables specified in this config file.
         */
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('MAIL_FROM_ADDRESS', 'info@algorah.bond'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'info@algorah.bond'),
                'name' => env('MAIL_FROM_NAME', 'FinTrack Backups'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',

            /*
             * If this is set to null the default channel of the webhook will be used.
             */
            'channel' => null,

            'username' => null,

            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',

            /*
             * If this is set to null the default channel of the webhook will be used.
             */
            'channel' => null,

            'username' => null,

            'avatar_url' => null,
        ],
    ],

    /*
     * Here you can specify which backups should be monitored.
     * If a backup does not meet the specified requirements the
     * UnhealthyBackupWasFound event will be fired.
     */
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'FinTrack'),
            'disks' => ['google'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        /*
         * The strategy that will be used to cleanup old backups. The default strategy
         * will keep all backups for a certain amount of time. After that time period
         * it will only keep a daily backup for a certain amount of time. Finally it
         * will only keep weekly backups for a certain amount of time and so on.
         *
         * NewBackupWillBeAdded will delete the oldest backup if the number of backups
         * exceeds the maximum number of backups.
         */
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [

            /*
             * The number of days for which backups must be kept.
             */
            'keep_all_backups_for_days' => 7,

            /*
             * The number of days for which daily backups must be kept.
             */
            'keep_daily_backups_for_days' => 16,

            /*
             * The number of weeks for which weekly backups must be kept.
             */
            'keep_weekly_backups_for_weeks' => 8,

            /*
             * The number of months for which monthly backups must be kept.
             */
            'keep_monthly_backups_for_months' => 4,

            /*
             * The number of years for which yearly backups must be kept.
             */
            'keep_yearly_backups_for_years' => 2,

            /*
             * After cleaning up, the oldest backup will be deleted until the amount of storage
             * used is below this amount.
             */
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];
