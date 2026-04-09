<?php

use App\Support\GitAutoUpdater;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:auto-update {--dry-run : Check update without pulling} {--force : Run even when AUTO_UPDATE_ENABLED=false}', function () {
    $result = app(GitAutoUpdater::class)->updateConfiguredBranch(
        (bool) $this->option('dry-run'),
        (bool) $this->option('force')
    );

    if (($result['status'] ?? '') === 'error') {
        $this->error((string) ($result['message'] ?? 'Auto update failed.'));

        return self::FAILURE;
    }

    if (($result['status'] ?? '') === 'update_available') {
        $this->info((string) ($result['message'] ?? 'Update available.'));

        return self::SUCCESS;
    }

    if (($result['status'] ?? '') === 'updated') {
        $this->info((string) ($result['message'] ?? 'Auto update completed.'));

        return self::SUCCESS;
    }

    $this->warn((string) ($result['message'] ?? 'Auto update skipped.'));

    return self::SUCCESS;
})->purpose('Fetch GitHub updates and fast-forward this app safely');

Schedule::command('app:auto-update')
    ->everyFiveMinutes()
    ->withoutOverlapping();
