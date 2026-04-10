<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    /**
     * Stream a zip archive of the current HEAD using `git archive`.
     * Disabled by default; enable with env ENABLE_SOURCE_DOWNLOAD=true
     */
    public function downloadLatest(Request $request)
    {
        if (!env('ENABLE_SOURCE_DOWNLOAD', false)) {
            return response()->json(['error' => 'source download disabled'], 403);
        }

        $filename = 'source-' . date('Ymd-His') . '.zip';

        $response = new StreamedResponse(function () {
            // ensure long-running
            set_time_limit(0);

            // use git archive to stream tracked files at HEAD as a zip
            $handle = popen('git archive --format=zip HEAD', 'r');
            if ($handle === false) {
                // fallback: output nothing
                return;
            }

            while (!feof($handle)) {
                echo fread($handle, 8192);
                @flush();
            }

            pclose($handle);
        });

        $disposition = 'attachment; filename="' . $filename . '"';
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
