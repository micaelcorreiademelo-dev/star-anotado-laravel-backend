<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for performance monitoring and metrics collection.
    |
    */

    'performance' => [
        'enabled' => env('MONITORING_PERFORMANCE_ENABLED', true),
        'slow_query_threshold' => env('MONITORING_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'memory_threshold' => env('MONITORING_MEMORY_THRESHOLD', 128), // MB
        'response_time_threshold' => env('MONITORING_RESPONSE_TIME_THRESHOLD', 2000), // milliseconds
        'collect_metrics' => env('MONITORING_COLLECT_METRICS', true),
        'metrics_retention_days' => env('MONITORING_METRICS_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for database performance monitoring.
    |
    */

    'database' => [
        'enabled' => env('MONITORING_DATABASE_ENABLED', true),
        'log_slow_queries' => env('MONITORING_LOG_SLOW_QUERIES', true),
        'slow_query_threshold' => env('MONITORING_DB_SLOW_QUERY_THRESHOLD', 500), // milliseconds
        'connection_monitoring' => env('MONITORING_DB_CONNECTION_MONITORING', true),
        'query_count_threshold' => env('MONITORING_DB_QUERY_COUNT_THRESHOLD', 100),
        'deadlock_detection' => env('MONITORING_DB_DEADLOCK_DETECTION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for security event monitoring and alerting.
    |
    */

    'security' => [
        'enabled' => env('MONITORING_SECURITY_ENABLED', true),
        'failed_login_threshold' => env('MONITORING_FAILED_LOGIN_THRESHOLD', 5),
        'failed_login_window' => env('MONITORING_FAILED_LOGIN_WINDOW', 300), // seconds
        'suspicious_activity_detection' => env('MONITORING_SUSPICIOUS_ACTIVITY', true),
        'ip_blocking' => [
            'enabled' => env('MONITORING_IP_BLOCKING_ENABLED', true),
            'block_duration' => env('MONITORING_IP_BLOCK_DURATION', 3600), // seconds
            'whitelist' => explode(',', env('MONITORING_IP_WHITELIST', '127.0.0.1,::1')),
        ],
        'rate_limiting' => [
            'enabled' => env('MONITORING_RATE_LIMITING_ENABLED', true),
            'requests_per_minute' => env('MONITORING_RATE_LIMIT_RPM', 60),
            'burst_limit' => env('MONITORING_RATE_LIMIT_BURST', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for log monitoring and analysis.
    |
    */

    'logs' => [
        'enabled' => env('MONITORING_LOGS_ENABLED', true),
        'error_threshold' => env('MONITORING_ERROR_THRESHOLD', 10), // errors per minute
        'warning_threshold' => env('MONITORING_WARNING_THRESHOLD', 50), // warnings per minute
        'log_levels_to_monitor' => explode(',', env('MONITORING_LOG_LEVELS', 'error,critical,alert,emergency')),
        'retention_days' => env('MONITORING_LOG_RETENTION_DAYS', 90),
        'compress_old_logs' => env('MONITORING_COMPRESS_OLD_LOGS', true),
        'real_time_analysis' => env('MONITORING_REAL_TIME_LOG_ANALYSIS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | System Metrics
    |--------------------------------------------------------------------------
    |
    | Configuration for system-level metrics collection.
    |
    */

    'metrics' => [
        'enabled' => env('MONITORING_METRICS_ENABLED', true),
        'collection_interval' => env('MONITORING_METRICS_INTERVAL', 60), // seconds
        'cpu_threshold' => env('MONITORING_CPU_THRESHOLD', 80), // percentage
        'memory_threshold' => env('MONITORING_MEMORY_THRESHOLD', 85), // percentage
        'disk_threshold' => env('MONITORING_DISK_THRESHOLD', 90), // percentage
        'network_monitoring' => env('MONITORING_NETWORK_ENABLED', true),
        'custom_metrics' => [
            'user_registrations' => env('MONITORING_TRACK_USER_REGISTRATIONS', true),
            'api_calls' => env('MONITORING_TRACK_API_CALLS', true),
            'file_uploads' => env('MONITORING_TRACK_FILE_UPLOADS', true),
            'cache_hits' => env('MONITORING_TRACK_CACHE_HITS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for alert notifications and escalation.
    |
    */

    'alerts' => [
        'enabled' => env('MONITORING_ALERTS_ENABLED', true),
        'channels' => [
            'email' => [
                'enabled' => env('MONITORING_EMAIL_ALERTS_ENABLED', true),
                'recipients' => explode(',', env('MONITORING_EMAIL_RECIPIENTS', '')),
                'severity_threshold' => env('MONITORING_EMAIL_SEVERITY_THRESHOLD', 'warning'),
            ],
            'slack' => [
                'enabled' => env('MONITORING_SLACK_ALERTS_ENABLED', false),
                'webhook_url' => env('MONITORING_SLACK_WEBHOOK_URL'),
                'channel' => env('MONITORING_SLACK_CHANNEL', '#alerts'),
                'severity_threshold' => env('MONITORING_SLACK_SEVERITY_THRESHOLD', 'error'),
            ],
            'webhook' => [
                'enabled' => env('MONITORING_WEBHOOK_ALERTS_ENABLED', false),
                'url' => env('MONITORING_WEBHOOK_URL'),
                'timeout' => env('MONITORING_WEBHOOK_TIMEOUT', 10),
                'retry_attempts' => env('MONITORING_WEBHOOK_RETRY_ATTEMPTS', 3),
            ],
        ],
        'escalation' => [
            'enabled' => env('MONITORING_ESCALATION_ENABLED', true),
            'escalation_delay' => env('MONITORING_ESCALATION_DELAY', 300), // seconds
            'max_escalation_level' => env('MONITORING_MAX_ESCALATION_LEVEL', 3),
        ],
        'quiet_hours' => [
            'enabled' => env('MONITORING_QUIET_HOURS_ENABLED', false),
            'start_time' => env('MONITORING_QUIET_HOURS_START', '22:00'),
            'end_time' => env('MONITORING_QUIET_HOURS_END', '08:00'),
            'timezone' => env('MONITORING_QUIET_HOURS_TIMEZONE', 'UTC'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    |
    | Configuration for application health monitoring.
    |
    */

    'health_checks' => [
        'enabled' => env('MONITORING_HEALTH_CHECKS_ENABLED', true),
        'interval' => env('MONITORING_HEALTH_CHECK_INTERVAL', 300), // seconds
        'timeout' => env('MONITORING_HEALTH_CHECK_TIMEOUT', 30), // seconds
        'checks' => [
            'database' => [
                'enabled' => env('MONITORING_HEALTH_CHECK_DATABASE', true),
                'timeout' => env('MONITORING_HEALTH_CHECK_DATABASE_TIMEOUT', 10),
            ],
            'cache' => [
                'enabled' => env('MONITORING_HEALTH_CHECK_CACHE', true),
                'timeout' => env('MONITORING_HEALTH_CHECK_CACHE_TIMEOUT', 5),
            ],
            'storage' => [
                'enabled' => env('MONITORING_HEALTH_CHECK_STORAGE', true),
                'timeout' => env('MONITORING_HEALTH_CHECK_STORAGE_TIMEOUT', 10),
            ],
            'external_apis' => [
                'enabled' => env('MONITORING_HEALTH_CHECK_EXTERNAL_APIS', true),
                'timeout' => env('MONITORING_HEALTH_CHECK_EXTERNAL_APIS_TIMEOUT', 15),
                'endpoints' => explode(',', env('MONITORING_HEALTH_CHECK_EXTERNAL_ENDPOINTS', '')),
            ],
        ],
        'failure_threshold' => env('MONITORING_HEALTH_CHECK_FAILURE_THRESHOLD', 3),
        'recovery_threshold' => env('MONITORING_HEALTH_CHECK_RECOVERY_THRESHOLD', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring reports and dashboards.
    |
    */

    'reporting' => [
        'enabled' => env('MONITORING_REPORTING_ENABLED', true),
        'daily_reports' => [
            'enabled' => env('MONITORING_DAILY_REPORTS_ENABLED', true),
            'time' => env('MONITORING_DAILY_REPORT_TIME', '09:00'),
            'recipients' => explode(',', env('MONITORING_DAILY_REPORT_RECIPIENTS', '')),
        ],
        'weekly_reports' => [
            'enabled' => env('MONITORING_WEEKLY_REPORTS_ENABLED', true),
            'day' => env('MONITORING_WEEKLY_REPORT_DAY', 'monday'),
            'time' => env('MONITORING_WEEKLY_REPORT_TIME', '09:00'),
            'recipients' => explode(',', env('MONITORING_WEEKLY_REPORT_RECIPIENTS', '')),
        ],
        'dashboard' => [
            'enabled' => env('MONITORING_DASHBOARD_ENABLED', true),
            'refresh_interval' => env('MONITORING_DASHBOARD_REFRESH_INTERVAL', 30), // seconds
            'public_access' => env('MONITORING_DASHBOARD_PUBLIC_ACCESS', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring data retention policies.
    |
    */

    'retention' => [
        'metrics_days' => env('MONITORING_METRICS_RETENTION_DAYS', 90),
        'logs_days' => env('MONITORING_LOGS_RETENTION_DAYS', 30),
        'alerts_days' => env('MONITORING_ALERTS_RETENTION_DAYS', 180),
        'health_checks_days' => env('MONITORING_HEALTH_CHECKS_RETENTION_DAYS', 60),
        'cleanup_enabled' => env('MONITORING_CLEANUP_ENABLED', true),
        'cleanup_schedule' => env('MONITORING_CLEANUP_SCHEDULE', '0 2 * * *'), // Daily at 2 AM
    ],

];