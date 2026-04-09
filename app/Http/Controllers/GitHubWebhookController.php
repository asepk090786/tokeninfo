<?php

namespace App\Http\Controllers;

use App\Support\GitAutoUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request, GitAutoUpdater $updater): JsonResponse
    {
        $secret = trim((string) config('auto_update.webhook_secret', ''));
        if ($secret === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'GITHUB_WEBHOOK_SECRET belum diatur di environment.',
            ], 503);
        }

        $payload = (string) $request->getContent();
        $signature = trim((string) $request->header('X-Hub-Signature-256', ''));

        if (! $this->hasValidSignature($payload, $secret, $signature)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Signature webhook tidak valid.',
            ], 401);
        }

        $event = strtolower(trim((string) $request->header('X-GitHub-Event', '')));
        $data = $request->json()->all();

        if ($event === 'ping') {
            return response()->json([
                'status' => 'ok',
                'message' => 'Webhook GitHub tersambung.',
                'zen' => $data['zen'] ?? null,
            ]);
        }

        if ($event !== 'push') {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Event ' . $event . ' diabaikan. Hanya event push yang diproses.',
            ], 202);
        }

        if (($data['deleted'] ?? false) === true) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Push untuk branch yang dihapus diabaikan.',
                'ref' => $data['ref'] ?? null,
            ], 202);
        }

        $ref = trim((string) ($data['ref'] ?? ''));
        if (! str_starts_with($ref, 'refs/heads/')) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Webhook hanya memproses branch refs/heads/*.',
                'ref' => $ref,
            ], 202);
        }

        $branch = substr($ref, strlen('refs/heads/'));
        $result = $updater->updateFromPush($branch);

        return response()->json(array_merge([
            'event' => $event,
            'ref' => $ref,
            'repository' => $data['repository']['full_name'] ?? null,
        ], $result), $this->statusCodeFor($result['status'] ?? 'error'));
    }

    private function hasValidSignature(string $payload, string $secret, string $signature): bool
    {
        if ($signature === '' || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function statusCodeFor(string $status): int
    {
        return match ($status) {
            'updated', 'up_to_date', 'update_available' => 200,
            'skipped', 'ignored' => 202,
            default => 500,
        };
    }
}