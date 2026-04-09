<?php

return [
    'enabled' => env('AUTO_UPDATE_ENABLED', false),
    'repo_path' => env('AUTO_UPDATE_REPO_PATH', base_path()),
    'remote' => env('AUTO_UPDATE_REMOTE', 'origin'),
    'branch' => env('AUTO_UPDATE_BRANCH', 'master'),
    'allowed_branches' => env('AUTO_UPDATE_ALLOWED_BRANCHES', '*'),
    'ignore_dirty_paths' => env('AUTO_UPDATE_IGNORE_DIRTY_PATHS', '.env,bootstrap/cache/.gitignore,storage/*.gitignore'),
    'lock_seconds' => env('AUTO_UPDATE_LOCK_SECONDS', 300),
    'run_composer' => env('AUTO_UPDATE_RUN_COMPOSER', false),
    'run_migrate' => env('AUTO_UPDATE_RUN_MIGRATE', false),
    'webhook_secret' => env('GITHUB_WEBHOOK_SECRET', ''),
];