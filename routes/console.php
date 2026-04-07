<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:auto-update {--dry-run : Check update without pulling} {--force : Run even when AUTO_UPDATE_ENABLED=false}', function () {
    $enabled = filter_var((string) env('AUTO_UPDATE_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
    if (! $enabled && ! $this->option('force')) {
        $this->line('Auto update disabled. Set AUTO_UPDATE_ENABLED=true to enable.');

        return self::SUCCESS;
    }

    $repoPath = rtrim((string) env('AUTO_UPDATE_REPO_PATH', base_path()), '/');
    $remote = trim((string) env('AUTO_UPDATE_REMOTE', 'origin')) ?: 'origin';
    $branch = trim((string) env('AUTO_UPDATE_BRANCH', 'master')) ?: 'master';
    $lockTtl = max(30, (int) env('AUTO_UPDATE_LOCK_SECONDS', 300));
    $runComposer = filter_var((string) env('AUTO_UPDATE_RUN_COMPOSER', 'false'), FILTER_VALIDATE_BOOL);
    $runMigrate = filter_var((string) env('AUTO_UPDATE_RUN_MIGRATE', 'false'), FILTER_VALIDATE_BOOL);
    $dryRun = (bool) $this->option('dry-run');

    $lock = Cache::lock('app_auto_update_lock', $lockTtl);
    if (! $lock->get()) {
        $this->warn('Auto update skipped: previous run is still in progress.');

        return self::SUCCESS;
    }

    $run = function (string $command, string $errorLabel) use ($repoPath): string {
        $result = Process::path($repoPath)->timeout(120)->run($command);
        if (! $result->successful()) {
            throw new RuntimeException($errorLabel . ': ' . trim($result->errorOutput() ?: $result->output()));
        }

        return trim($result->output());
    };

    try {
        $insideGit = $run('git rev-parse --is-inside-work-tree', 'Git repository check failed');
        if ($insideGit !== 'true') {
            throw new RuntimeException('Target path is not a git repository: ' . $repoPath);
        }

        $dirtyOutput = $run('git status --porcelain', 'Working tree check failed');
        if ($dirtyOutput !== '') {
            $this->warn('Auto update skipped: working tree has local changes.');

            return self::SUCCESS;
        }

        $run('git fetch --quiet ' . escapeshellarg($remote) . ' ' . escapeshellarg($branch), 'Git fetch failed');

        $localHash = $run('git rev-parse HEAD', 'Reading local commit failed');
        $remoteHash = $run('git rev-parse ' . escapeshellarg($remote . '/' . $branch), 'Reading remote commit failed');

        if ($localHash === $remoteHash) {
            $this->line('No updates found. Local is up to date at ' . $localHash . '.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Update available: ' . $localHash . ' -> ' . $remoteHash);

            return self::SUCCESS;
        }

        $this->line('Updating from ' . $localHash . ' to ' . $remoteHash . ' ...');
        $run('git pull --ff-only ' . escapeshellarg($remote) . ' ' . escapeshellarg($branch), 'Git pull failed');

        $newHash = $run('git rev-parse HEAD', 'Reading updated commit failed');

        if ($runComposer) {
            $this->line('Running composer install ...');
            $run('composer install --no-interaction --prefer-dist --optimize-autoloader', 'Composer install failed');
        }

        if ($runMigrate) {
            $this->line('Running php artisan migrate --force ...');
            $run('php artisan migrate --force', 'Database migration failed');
        }

        $run('php artisan optimize:clear', 'Application cache clear failed');

        $this->info('Auto update completed successfully at commit ' . $newHash . '.');

        return self::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Auto update failed: ' . $e->getMessage());

        return self::FAILURE;
    } finally {
        optional($lock)->release();
    }
})->purpose('Fetch GitHub updates and fast-forward this app safely');

Schedule::command('app:auto-update')
    ->everyFiveMinutes()
    ->withoutOverlapping();
