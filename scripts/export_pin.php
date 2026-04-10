<?php
// Export current CBT token from DB to public/pin.json
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $row = DB::table('cbt_token')->where('id_token', 1)->first();

    $token = strtoupper((string) ($row->token ?? 'BELUM-DISET'));
    $updated = $row->updated ?? null;
    $jarak = isset($row->jarak) ? (int) $row->jarak : 0;

    $generatedAt = $updated ? (new DateTime($updated))->format(DateTime::ATOM) : null;
    $validUntil = $updated ? (new DateTime($updated))->add(new DateInterval('PT' . $jarak . 'M'))->format(DateTime::ATOM) : null;

    $data = [
        'pin' => $token,
        'generated_at' => $generatedAt,
        'valid_until' => $validUntil,
        'source' => 'db',
    ];

    $target = __DIR__ . '/../public/pin.json';
    file_put_contents($target, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Invalidate application cache keys so web UI reads fresh DB values
    try {
        \Illuminate\Support\Facades\Cache::forget('cbt_info_payload');
        \Illuminate\Support\Facades\Cache::forget('cbt_token_row:id:1');
    } catch (Throwable $e) {
        // ignore cache clearing errors in CLI script
    }

    echo "Wrote {$target}\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
