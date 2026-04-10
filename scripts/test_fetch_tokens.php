<?php
// Simple CLI script to fetch token.json and version.token.json from each mirror listed
// Usage: php scripts/test_fetch_tokens.php

$base = __DIR__ . '/../storage/app/private/mirror_list.json';
if (! file_exists($base)) {
    echo "mirror_list.json not found: $base\n";
    exit(1);
}
$raw = file_get_contents($base);
$decoded = json_decode($raw, true);
if (! is_array($decoded) || empty($decoded['mirrors'])) {
    echo "mirror_list.json invalid or no mirrors\n";
    exit(1);
}
$mirrors = $decoded['mirrors'];

// Try to fetch settings from DB (web_settings) if available
$pdo = null;
$dbApiKey = '';
$tokenEndpoint = '/token.json';
$tokenVersionEndpoint = '/version.token.json';

// Attempt to read DB config from .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $envMap = [];
    foreach ($env as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $envMap[$parts[0]] = trim($parts[1]);
        }
    }

    if (! empty($envMap['DB_CONNECTION']) && ! empty($envMap['DB_DATABASE'])) {
        $driver = $envMap['DB_CONNECTION'];
        $db = $envMap['DB_DATABASE'];
        $user = $envMap['DB_USERNAME'] ?? '';
        $pass = $envMap['DB_PASSWORD'] ?? '';
        $host = $envMap['DB_HOST'] ?? '127.0.0.1';
        $port = $envMap['DB_PORT'] ?? '';

        try {
            if ($driver === 'mysql') {
                $dsn = "mysql:host={$host};dbname={$db}" . (!empty($port) ? ";port={$port}" : '');
                $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }
        } catch (Exception $e) {
            // ignore DB errors; we'll continue without DB
        }
    }
}

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM web_settings WHERE setting_key = :k LIMIT 1");
        $keys = ['cbt_exambro_api_key','cbt_token_endpoint','cbt_token_version_endpoint','cbt_load_balancing_url'];
        foreach ($keys as $k) {
            $stmt->execute([':k' => $k]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['setting_value'])) {
                $value = json_decode($row['setting_value'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $val = $value;
                } else {
                    $val = $row['setting_value'];
                }

                if ($k === 'cbt_exambro_api_key') $dbApiKey = (string) $val;
                if ($k === 'cbt_token_endpoint' && ! empty($val)) $tokenEndpoint = (string) $val;
                if ($k === 'cbt_token_version_endpoint' && ! empty($val)) $tokenVersionEndpoint = (string) $val;
            }
        }
    } catch (Exception $e) {
        // ignore
    }
}

echo "Using token endpoint: {$tokenEndpoint}\n";
echo "Using version token endpoint: {$tokenVersionEndpoint}\n";
echo "Using API key from settings: " . ($dbApiKey !== '' ? '[present]' : '[empty]') . "\n\n";

function join_url($base, $path) {
    $base = rtrim($base, '/');
    $path = ltrim($path, '/');
    if (preg_match('#^https?://#i', $path)) return $path;
    return $base . '/' . $path;
}

$counter = 0;
foreach ($mirrors as $m) {
    $counter++;
    $name = $m['name'] ?? ($m['key'] ?? 'server');
    $url = rtrim($m['url'] ?? '', '/');
    if ($url === '') continue;

    echo "== Mirror {$counter}: {$name} ({$url}) ==\n";

    $eps = [ $tokenEndpoint, $tokenVersionEndpoint ];
    foreach ($eps as $ep) {
        $final = '';
        if (strpos($ep, '{api_key}') !== false) {
            $final = str_replace('{api_key}', rawurlencode($dbApiKey), $ep);
            if (! preg_match('#^https?://#i', $final)) {
                // relative
                $final = join_url($url, $final);
            }
        } else {
            // If endpoint is full URL
            if (preg_match('#^https?://#i', $ep)) {
                $final = $ep;
                $sep = strpos($final, '?') !== false ? '&' : '?';
                $final .= $sep . 'api_key=' . rawurlencode($dbApiKey);
            } else {
                // relative
                $final = join_url($url, $ep) . (strpos($ep, '?') !== false ? '&' : '?') . 'api_key=' . rawurlencode($dbApiKey);
            }
        }

        echo "Requesting: {$final}\n";

        // Use curl
        $ch = curl_init($final);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TokenInfoTest/1.0');
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($errno !== 0) {
            echo "  curl error: {$errno}\n\n";
            continue;
        }

        $http = $info['http_code'] ?? 0;
        echo "  HTTP status: {$http}\n";
        $trim = trim((string)$body);
        if ($trim === '') {
            echo "  Empty body\n\n";
            continue;
        }

        // Try decode JSON
        $d = json_decode($trim, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "  JSON body: " . substr(json_encode($d), 0, 1000) . "\n\n";
        } else {
            echo "  Non-JSON body (first 500 chars):\n" . substr($trim, 0, 500) . "\n\n";
        }
    }

    if ($counter >= 100) break; // safety
}

echo "Done.\n";
