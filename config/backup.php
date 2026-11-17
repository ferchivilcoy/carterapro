<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MySQL / MariaDB Dump Binary
    |--------------------------------------------------------------------------
    |
    | When the application runs on MySQL or MariaDB the backup shells out to
    | mysqldump. It usually lives in PATH (Laragon, Laravel Herd and most Linux
    | distributions include it), but set DB_DUMP_BINARY to an absolute path when
    | it does not, so a scheduled backup never fails silently.
    |
    */

    'mysql_dump_binary' => env('DB_DUMP_BINARY', 'mysqldump'),

    /*
    |--------------------------------------------------------------------------
    | Dump Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds a mysqldump run may take before it is treated
    | as stuck. A dump that exceeds it fails the backup instead of hanging the
    | scheduled task indefinitely.
    |
    */

    'timeout' => (int) env('DB_DUMP_TIMEOUT', 300),

];
