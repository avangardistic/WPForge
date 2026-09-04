<?php
return [
    'enabled'           => true,
    'developer_mode'    => false,
    'filesystem'        => [
        'root'            => ABSPATH,
        'allowed_paths'   => [],
        'allow_writes'    => false,
        'allow_deletes'   => false,
        'max_file_size'   => 10 * 1024 * 1024,
    ],
    'database'          => [
        'allow_writes'    => false,
        'max_rows'        => 1000,
        'query_timeout'   => 30,
        'read_only'       => true,
    ],
    'backup'            => [
        'max_backups'     => 10,
        'retention_days'  => 30,
    ],
    'logging'           => [
        'enabled'         => true,
        'level'           => 'info',
        'max_files'       => 10,
        'max_size'        => 10 * 1024 * 1024,
    ],
    'rate_limits'       => [
        'window'          => 60,
        'max_requests'    => 100,
    ],
    'security'          => [
        'cors_origins'    => [],
        'allowed_ips'     => [],
        'blocked_ips'     => [],
    ],
    'elementor'         => [
        'allow_modifications' => true,
        'create_backup_before_update' => true,
    ],
];
