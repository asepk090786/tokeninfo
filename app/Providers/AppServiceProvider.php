<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $resolveExambroSignature = static function (Request $request): string {
            $ip = (string) $request->ip();
            $ua = strtolower(trim((string) $request->userAgent()));

            return sha1(($ip !== '' ? $ip : 'no-ip') . '|' . ($ua !== '' ? $ua : 'no-ua'));
        };

        RateLimiter::for('exambro-api', function (Request $request) use ($resolveExambroSignature) {
            $signature = $resolveExambroSignature($request);
            $perKeyLimit = max(120, (int) env('EXAMBRO_API_LIMIT_PER_MINUTE', 2400));
            $perIpLimit = max(120, (int) env('EXAMBRO_API_IP_LIMIT_PER_MINUTE', 1200));

            return [
                // Allow large classrooms behind one NAT while still capping source IP bursts.
                Limit::perMinute($perKeyLimit)->by('exambro-key|' . $signature),
                Limit::perMinute($perIpLimit)->by('exambro-ip|' . (string) $request->ip()),
            ];
        });

        RateLimiter::for('public-api', function (Request $request) {
            $perIpLimit = max(30, (int) env('PUBLIC_API_IP_LIMIT_PER_MINUTE', 60));

            return [
                Limit::perMinute($perIpLimit)->by('public-ip|' . (string) $request->ip()),
            ];
        });

        // Heartbeat dikirim saat user memilih server; kelas besar bisa memicu burst serentak.
        RateLimiter::for('presence-heartbeat', function (Request $request) use ($resolveExambroSignature) {
            $signature = $resolveExambroSignature($request);
            $perKeyLimit = max(60, (int) env('EXAMBRO_HEARTBEAT_LIMIT_PER_MINUTE', 1200));
            $perIpLimit = max(30, (int) env('EXAMBRO_HEARTBEAT_IP_LIMIT_PER_MINUTE', 600));

            return [
                Limit::perMinute($perKeyLimit)->by('heartbeat-key|' . $signature),
                Limit::perMinute($perIpLimit)->by('heartbeat-ip|' . (string) $request->ip()),
            ];
        });
    }
}
