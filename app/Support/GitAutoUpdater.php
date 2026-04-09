<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class GitAutoUpdater
{
    private string $repoPath;

    private string $remote;

    private string $defaultBranch;

    private int $lockTtl;

    private bool $enabled;

    private bool $runComposer;

    private bool $runMigrate;

    /**
     * @var array<int, string>
     */
    private array $dirtyIgnorePatterns;

    public function __construct()
    {
        $this->repoPath = rtrim((string) env('AUTO_UPDATE_REPO_PATH', base_path()), '/');
        $this->remote = trim((string) env('AUTO_UPDATE_REMOTE', 'origin')) ?: 'origin';
        $this->defaultBranch = trim((string) env('AUTO_UPDATE_BRANCH', 'master')) ?: 'master';
        $this->lockTtl = max(30, (int) env('AUTO_UPDATE_LOCK_SECONDS', 300));
        $this->enabled = filter_var((string) env('AUTO_UPDATE_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
        $this->runComposer = filter_var((string) env('AUTO_UPDATE_RUN_COMPOSER', 'false'), FILTER_VALIDATE_BOOL);
        $this->runMigrate = filter_var((string) env('AUTO_UPDATE_RUN_MIGRATE', 'false'), FILTER_VALIDATE_BOOL);
        $this->dirtyIgnorePatterns = $this->parseDirtyIgnorePatterns();
    }

    public function updateConfiguredBranch(bool $dryRun = false, bool $force = false): array
    {
        if (! $this->enabled && ! $force) {
            return [
                'status' => 'skipped',
                'message' => 'Auto update disabled. Set AUTO_UPDATE_ENABLED=true to enable.',
            ];
        }

        return $this->withLock(function () use ($dryRun) {
            $this->assertInsideGitRepository();

            $currentBranch = $this->getCurrentBranch();
            if ($currentBranch !== $this->defaultBranch) {
                return [
                    'status' => 'skipped',
                    'message' => 'Checked out branch is ' . ($currentBranch !== '' ? $currentBranch : 'detached HEAD') . ', expected ' . $this->defaultBranch . '.',
                    'branch' => $this->defaultBranch,
                    'current_branch' => $currentBranch,
                ];
            }

            $blockingDirtyPaths = $this->getBlockingDirtyPaths();
            if ($blockingDirtyPaths !== []) {
                return [
                    'status' => 'skipped',
                    'message' => 'Auto update skipped: working tree has local changes.',
                    'branch' => $this->defaultBranch,
                    'current_branch' => $currentBranch,
                    'dirty_paths' => $blockingDirtyPaths,
                ];
            }

            $this->fetchBranch($this->defaultBranch);

            $localHash = $this->gitOutput('git rev-parse HEAD', 'Reading local commit failed');
            $remoteHash = $this->gitOutput(
                'git rev-parse ' . escapeshellarg($this->remoteRef($this->defaultBranch)),
                'Reading remote commit failed'
            );

            if ($localHash === $remoteHash) {
                return [
                    'status' => 'up_to_date',
                    'message' => 'No updates found. Local is up to date at ' . $localHash . '.',
                    'branch' => $this->defaultBranch,
                    'current_branch' => $currentBranch,
                    'local_hash' => $localHash,
                    'remote_hash' => $remoteHash,
                ];
            }

            if ($dryRun) {
                return [
                    'status' => 'update_available',
                    'message' => 'Update available: ' . $localHash . ' -> ' . $remoteHash,
                    'branch' => $this->defaultBranch,
                    'current_branch' => $currentBranch,
                    'local_hash' => $localHash,
                    'remote_hash' => $remoteHash,
                ];
            }

            $this->gitOutput(
                'git pull --ff-only ' . escapeshellarg($this->remote) . ' ' . escapeshellarg($this->defaultBranch),
                'Git pull failed'
            );
            $newHash = $this->gitOutput('git rev-parse HEAD', 'Reading updated commit failed');

            $steps = $this->runPostUpdateSteps();

            return [
                'status' => 'updated',
                'message' => 'Auto update completed successfully at commit ' . $newHash . '.',
                'branch' => $this->defaultBranch,
                'current_branch' => $currentBranch,
                'local_hash' => $localHash,
                'remote_hash' => $remoteHash,
                'new_hash' => $newHash,
                'steps' => $steps,
            ];
        }, [
            'mode' => 'scheduled',
            'branch' => $this->defaultBranch,
            'dry_run' => $dryRun,
        ]);
    }

    public function updateFromPush(string $branch): array
    {
        if (! $this->enabled) {
            return [
                'status' => 'skipped',
                'message' => 'Auto update disabled. Set AUTO_UPDATE_ENABLED=true to enable webhook updates.',
                'branch' => $branch,
            ];
        }

        if (! $this->isAllowedBranch($branch)) {
            return [
                'status' => 'skipped',
                'message' => 'Branch ' . $branch . ' is not allowed by AUTO_UPDATE_ALLOWED_BRANCHES.',
                'branch' => $branch,
            ];
        }

        return $this->withLock(function () use ($branch) {
            $this->assertValidBranch($branch);
            $this->assertInsideGitRepository();
            $this->fetchAllBranches();

            $currentBranch = $this->getCurrentBranch();
            $remoteRef = $this->remoteRef($branch);
            $remoteHash = $this->gitOutput(
                'git rev-parse ' . escapeshellarg($remoteRef),
                'Reading remote branch commit failed'
            );

            $branchAction = 'none';
            if (! $this->localBranchExists($branch)) {
                $this->gitOutput(
                    'git branch --track ' . escapeshellarg($branch) . ' ' . escapeshellarg($remoteRef),
                    'Creating local tracking branch failed'
                );
                $branchAction = 'created_tracking_branch';
            }

            $comparison = $this->compareBranchToRemote($branch, $remoteRef);
            $ahead = $comparison['ahead'];
            $behind = $comparison['behind'];
            $localHash = $this->gitOutput('git rev-parse ' . escapeshellarg($branch), 'Reading local branch commit failed');

            if ($currentBranch === $branch) {
                $blockingDirtyPaths = $this->getBlockingDirtyPaths();
                if ($blockingDirtyPaths !== []) {
                    return [
                        'status' => 'skipped',
                        'message' => 'Push received for active branch, but working tree has local changes so pull was skipped.',
                        'branch' => $branch,
                        'current_branch' => $currentBranch,
                        'local_hash' => $localHash,
                        'remote_hash' => $remoteHash,
                        'branch_action' => $branchAction,
                        'dirty_paths' => $blockingDirtyPaths,
                    ];
                }

                if ($ahead > 0 && $behind > 0) {
                    return [
                        'status' => 'skipped',
                        'message' => 'Active branch ' . $branch . ' has diverged from ' . $remoteRef . '. Fast-forward skipped.',
                        'branch' => $branch,
                        'current_branch' => $currentBranch,
                        'local_hash' => $localHash,
                        'remote_hash' => $remoteHash,
                        'branch_action' => $branchAction,
                    ];
                }

                if ($ahead > 0 && $behind === 0) {
                    return [
                        'status' => 'skipped',
                        'message' => 'Active branch ' . $branch . ' has local commits ahead of ' . $remoteRef . '. Fast-forward skipped.',
                        'branch' => $branch,
                        'current_branch' => $currentBranch,
                        'local_hash' => $localHash,
                        'remote_hash' => $remoteHash,
                        'branch_action' => $branchAction,
                    ];
                }

                if ($behind === 0) {
                    return [
                        'status' => $branchAction === 'created_tracking_branch' ? 'updated' : 'up_to_date',
                        'message' => $branchAction === 'created_tracking_branch'
                            ? 'Tracking branch ' . $branch . ' created and already aligned with ' . $remoteRef . '.'
                            : 'Branch ' . $branch . ' is already up to date.',
                        'branch' => $branch,
                        'current_branch' => $currentBranch,
                        'local_hash' => $localHash,
                        'remote_hash' => $remoteHash,
                        'branch_action' => $branchAction,
                    ];
                }

                $this->gitOutput(
                    'git pull --ff-only ' . escapeshellarg($this->remote) . ' ' . escapeshellarg($branch),
                    'Fast-forward pull failed for active branch'
                );
                $newHash = $this->gitOutput('git rev-parse HEAD', 'Reading updated HEAD failed');
                $steps = $this->runPostUpdateSteps();

                return [
                    'status' => 'updated',
                    'message' => 'Active branch ' . $branch . ' fast-forwarded successfully via webhook.',
                    'branch' => $branch,
                    'current_branch' => $currentBranch,
                    'local_hash' => $localHash,
                    'remote_hash' => $remoteHash,
                    'new_hash' => $newHash,
                    'branch_action' => $branchAction === 'none' ? 'fast_forward_pull' : $branchAction . '+fast_forward_pull',
                    'steps' => $steps,
                ];
            }

            if ($ahead > 0 && $behind > 0) {
                return [
                    'status' => 'skipped',
                    'message' => 'Local branch ' . $branch . ' has diverged from ' . $remoteRef . '. Reference update skipped.',
                    'branch' => $branch,
                    'current_branch' => $currentBranch,
                    'local_hash' => $localHash,
                    'remote_hash' => $remoteHash,
                    'branch_action' => $branchAction,
                ];
            }

            if ($ahead > 0 && $behind === 0) {
                return [
                    'status' => 'skipped',
                    'message' => 'Local branch ' . $branch . ' has commits ahead of ' . $remoteRef . '. Reference update skipped.',
                    'branch' => $branch,
                    'current_branch' => $currentBranch,
                    'local_hash' => $localHash,
                    'remote_hash' => $remoteHash,
                    'branch_action' => $branchAction,
                ];
            }

            if ($behind === 0) {
                return [
                    'status' => $branchAction === 'created_tracking_branch' ? 'updated' : 'up_to_date',
                    'message' => $branchAction === 'created_tracking_branch'
                        ? 'Tracking branch ' . $branch . ' created from ' . $remoteRef . '.'
                        : 'Branch ' . $branch . ' is already up to date.',
                    'branch' => $branch,
                    'current_branch' => $currentBranch,
                    'local_hash' => $localHash,
                    'remote_hash' => $remoteHash,
                    'branch_action' => $branchAction,
                ];
            }

            $this->gitOutput(
                'git branch -f ' . escapeshellarg($branch) . ' ' . escapeshellarg($remoteRef),
                'Fast-forwarding local branch reference failed'
            );
            $newHash = $this->gitOutput('git rev-parse ' . escapeshellarg($branch), 'Reading updated local branch commit failed');

            return [
                'status' => 'updated',
                'message' => 'Local branch ' . $branch . ' reference updated to latest ' . $remoteRef . '.',
                'branch' => $branch,
                'current_branch' => $currentBranch,
                'local_hash' => $localHash,
                'remote_hash' => $remoteHash,
                'new_hash' => $newHash,
                'branch_action' => $branchAction === 'none' ? 'fast_forward_ref' : $branchAction . '+fast_forward_ref',
            ];
        }, [
            'mode' => 'webhook',
            'branch' => $branch,
        ]);
    }

    private function withLock(callable $callback, array $context): array
    {
        $lock = Cache::lock('app_auto_update_lock', $this->lockTtl);
        if (! $lock->get()) {
            return [
                'status' => 'skipped',
                'message' => 'Auto update skipped: previous run is still in progress.',
                'branch' => $context['branch'] ?? null,
            ];
        }

        try {
            $result = $callback();
            Log::info('Git auto update completed.', array_merge($context, $result));

            return $result;
        } catch (Throwable $e) {
            Log::error('Git auto update failed.', array_merge($context, [
                'exception' => $e->getMessage(),
            ]));

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'branch' => $context['branch'] ?? null,
            ];
        } finally {
            $lock->release();
        }
    }

    private function assertInsideGitRepository(): void
    {
        $insideGit = $this->gitOutput('git rev-parse --is-inside-work-tree', 'Git repository check failed');
        if ($insideGit !== 'true') {
            throw new \RuntimeException('Target path is not a git repository: ' . $this->repoPath);
        }
    }

    private function assertValidBranch(string $branch): void
    {
        $result = Process::path($this->repoPath)
            ->timeout(30)
            ->run('git check-ref-format --branch ' . escapeshellarg($branch));

        if (! $result->successful()) {
            throw new \RuntimeException('Invalid branch name: ' . $branch);
        }
    }

    private function fetchBranch(string $branch): void
    {
        $this->gitOutput(
            'git fetch --prune --quiet ' . escapeshellarg($this->remote) . ' ' . escapeshellarg($branch),
            'Git fetch failed'
        );
    }

    private function fetchAllBranches(): void
    {
        $this->gitOutput(
            'git fetch --prune --quiet ' . escapeshellarg($this->remote) . ' ' . escapeshellarg('+refs/heads/*:refs/remotes/' . $this->remote . '/*'),
            'Git fetch for all branches failed'
        );
    }

    private function getCurrentBranch(): string
    {
        return $this->gitOutput('git branch --show-current', 'Reading current branch failed');
    }

    private function getBlockingDirtyPaths(): array
    {
        $output = $this->gitOutput('git status --porcelain', 'Working tree check failed');
        if ($output === '') {
            return [];
        }

        $blockingPaths = [];
        foreach (preg_split('/\r?\n/', $output) ?: [] as $line) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            $path = $this->extractDirtyPath($line);
            if ($path === '' || $this->pathMatchesIgnorePattern($path)) {
                continue;
            }

            $blockingPaths[] = $path;
        }

        return array_values(array_unique($blockingPaths));
    }

    private function extractDirtyPath(string $line): string
    {
        $path = trim(substr($line, 3));
        if ($path === '') {
            return '';
        }

        if (str_contains($path, ' -> ')) {
            $parts = explode(' -> ', $path);
            $path = trim((string) end($parts));
        }

        return $path;
    }

    private function pathMatchesIgnorePattern(string $path): bool
    {
        foreach ($this->dirtyIgnorePatterns as $pattern) {
            if ($pattern !== '' && fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function parseDirtyIgnorePatterns(): array
    {
        $configured = trim((string) env(
            'AUTO_UPDATE_IGNORE_DIRTY_PATHS',
            '.env,bootstrap/cache/.gitignore,storage/*.gitignore'
        ));

        return array_values(array_filter(array_map('trim', explode(',', $configured))));
    }

    private function localBranchExists(string $branch): bool
    {
        $result = Process::path($this->repoPath)
            ->timeout(30)
            ->run('git show-ref --verify --quiet ' . escapeshellarg('refs/heads/' . $branch));

        return $result->successful();
    }

    private function compareBranchToRemote(string $branch, string $remoteRef): array
    {
        $counts = $this->gitOutput(
            'git rev-list --left-right --count ' . escapeshellarg($branch . '...' . $remoteRef),
            'Comparing branch state failed'
        );
        [$ahead, $behind] = preg_split('/\s+/', trim($counts)) + [0, 0];

        return [
            'ahead' => (int) $ahead,
            'behind' => (int) $behind,
        ];
    }

    private function runPostUpdateSteps(): array
    {
        $steps = [];

        if ($this->runComposer) {
            $this->gitOutput(
                'composer install --no-interaction --prefer-dist --optimize-autoloader',
                'Composer install failed',
                600
            );
            $steps[] = 'composer_install';
        }

        if ($this->runMigrate) {
            $this->gitOutput('php artisan migrate --force', 'Database migration failed', 300);
            $steps[] = 'artisan_migrate';
        }

        $this->gitOutput('php artisan optimize:clear', 'Application cache clear failed', 180);
        $steps[] = 'artisan_optimize_clear';

        return $steps;
    }

    private function isAllowedBranch(string $branch): bool
    {
        $allowed = trim((string) env('AUTO_UPDATE_ALLOWED_BRANCHES', '*'));
        if ($allowed === '' || $allowed === '*') {
            return true;
        }

        $branches = array_values(array_filter(array_map('trim', explode(',', $allowed))));

        return in_array($branch, $branches, true);
    }

    private function remoteRef(string $branch): string
    {
        return $this->remote . '/' . $branch;
    }

    private function gitOutput(string $command, string $errorLabel, int $timeout = 120): string
    {
        $result = Process::path($this->repoPath)->timeout($timeout)->run($command);
        if (! $result->successful()) {
            throw new \RuntimeException($errorLabel . ': ' . trim($result->errorOutput() ?: $result->output()));
        }

        return trim($result->output());
    }
}